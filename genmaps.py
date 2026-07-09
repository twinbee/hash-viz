#!/usr/bin/env python3
"""
genmaps.py - Generate historical HHH maps for dfwhhh.org/Maps.

Resolves a lat/lon for EVERY event location, drawing from every format the
calendar uses:
  * raw lat/lon embedded in the START column         e.g. "... (32.7549, -97.08)"
  * Open Location "plus codes" / grid squares        e.g. "53M4+4X Little Elm, TX"
  * Google / Bing / short map links (MAP column)     goo.gl, maps.app.goo.gl,
    g.page, binged.it ... expanded, then coords or a place-name pulled out
  * partial / named places ("Adair's Saloon")        geocoded with DFW context

All lookups are cached to disk (coords_cache.json / url_cache.json) so re-runs
are cheap and we stay well under Nominatim's rate limit.

Outputs interactive folium maps to the Maps/ output dir:
  allRuns_2012-<cut>.html   master map (colour-coded by kennel)
  <year>.html               one per calendar year
  <kennel>.html             dallas / ftw / duh / noduh / fullmoon / bike / dumb / yak

Usage:
  python genmaps.py <android_dir> --out <maps_dir> [--cut 2026-07] [--retry-failed]
  python genmaps.py <android_dir> --out <maps_dir> --resolve-only
"""

import os, re, csv, json, glob, time, argparse, html, sys

import requests
from geopy.geocoders import Nominatim
from geopy.extra.rate_limiter import RateLimiter
from geopy.distance import geodesic
import folium

try:
    from openlocationcode import openlocationcode as olc
except Exception:
    olc = None

DALLAS = (32.7767, -96.7970)
MAX_MI = 160                       # reject geocodes further than this from Dallas
UA = "dfwhhh.org historical hash maps (contact: matt.bennett@missecurity.com)"

# --------------------------------------------------------------------------
# Canonical kennels.  Order matters: first matching rule wins, so the most
# specific patterns (DUMB before DUH, Ft Worth before Dallas) come first.
# `file` names the per-kennel map that kennel belongs on (None = master only).
# --------------------------------------------------------------------------
KENNELS = [
    dict(key="dumb",     label="Dallas Urban Mountain Bike (DUMB)", color="#c99a00",
         file="dumb", pats=[r"\bDUMB\b", r"urban mountain bike"]),
    dict(key="bike",     label="Bike Hash (B3H3 / BASH)",           color="#f2d600",
         file="bike", pats=[r"\bbike\b", r"\bB3H3\b", r"\bBASH\b"]),
    dict(key="yak",      label="YaK / Kayak H3",                    color="#20c0c0",
         file="yak",  pats=[r"\byak", r"\bkayak"]),
    dict(key="duh",      label="Dallas Urban Hash (DUH)",           color="#8e2de2",
         file="duh",  pats=[r"dallas urban hash", r"\bDUH\b", r"\bD\.?U\.?H\b"]),
    dict(key="noduh",    label="NODUH",                             color="#f39200",
         file="noduh", pats=[r"n[o0]-?no-?duh", r"\bNODUH\b", r"no-?duh"]),
    dict(key="ftw",      label="Fort Worth H3",                     color="#e01f1f",
         file="ftw",  pats=[r"\bfort worth\b", r"\bft\.? worth\b", r"\bFWH3\b",
                            r"cowtown", r"\bFW\b"]),
    dict(key="fullmoon", label="Full Moon",                         color="#111111",
         file="fullmoon", pats=[r"full moon", r"monty moon"]),
    dict(key="dallas",   label="Dallas H3",                         color="#1f6fe0",
         file="dallas", pats=[r"dallas hash", r"\bdallas h3\b", r"triple d"]),
    dict(key="grapevine",label="Grapevine Quarterly",               color="#b14fd8",
         file=None,   pats=[r"grapevine"]),
    # everything social / weeknight falls through to here (master map only, green)
    dict(key="other",    label="Happy Hour / Trivia / Social / other", color="#2e9e2e",
         file=None,   pats=[r".*"]),
]
for k in KENNELS:
    k["re"] = [re.compile(p, re.IGNORECASE) for p in k["pats"]]


def classify(kennel_name):
    name = kennel_name or ""
    for k in KENNELS:
        if any(rx.search(name) for rx in k["re"]):
            return k
    return KENNELS[-1]


# --------------------------------------------------------------------------
# Caches
# --------------------------------------------------------------------------
def load_json(path):
    if os.path.exists(path):
        try:
            with open(path, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return {}
    return {}


def save_json(path, obj):
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(obj, f)
    os.replace(tmp, path)


# --------------------------------------------------------------------------
# Location string cleaning
# --------------------------------------------------------------------------
def clean_text(s):
    if not s:
        return ""
    s = re.sub(r"<br\s*/?>", " ", s, flags=re.IGNORECASE)
    s = re.sub(r"<[^>]+>", " ", s)                 # strip any other html
    s = html.unescape(s)
    s = re.sub(r"\bStart\b", " ", s, flags=re.IGNORECASE)
    s = re.sub(r"[^\x00-\x7F]+", " ", s)           # drop non-ascii
    s = re.sub(r"\s+", " ", s).strip(" ,-")
    return s


LATLON_RE = re.compile(r"(-?\d{1,2}\.\d{3,}),\s*(-?\d{2,3}\.\d{3,})")
PLUS_RE = re.compile(r"\b([23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{2,3})\b")


def near_dallas(lat, lon):
    try:
        return geodesic((lat, lon), DALLAS).miles <= MAX_MI
    except Exception:
        return False


def parse_latlon(text):
    """Raw 'lat, lon' embedded anywhere in a string (DFW-plausible only)."""
    for m in LATLON_RE.finditer(text or ""):
        lat, lon = float(m.group(1)), float(m.group(2))
        # DFW is ~ +32, -96/-97.  Guard against zip codes etc.
        if 30 <= lat <= 35 and -99 <= lon <= -94 and near_dallas(lat, lon):
            return (lat, lon)
    return None


def parse_pluscode(text):
    if not olc:
        return None
    m = PLUS_RE.search(text or "")
    if not m:
        return None
    code = m.group(1)
    try:
        if olc.isFull(code):
            full = code
        elif olc.isShort(code):
            full = olc.recoverNearest(code, DALLAS[0], DALLAS[1])
        else:
            return None
        a = olc.decode(full)
        c = (a.latitudeCenter, a.longitudeCenter)
        return c if near_dallas(*c) else None
    except Exception:
        return None


# --------------------------------------------------------------------------
# URL handling
# --------------------------------------------------------------------------
SHORTENERS = ("goo.gl", "g.co", "g.page", "maps.app.goo.gl", "binged.it",
              "tinyurl.com", "mapq.st", "bit.ly", "l.facebook.com", "youtu.be")


def needs_expand(url):
    return any(s in url for s in SHORTENERS)


def expand_url(url, ucache):
    if url in ucache:
        return ucache[url]
    result = url
    try:
        r = requests.head(url, allow_redirects=True, timeout=10,
                          headers={"User-Agent": UA})
        if r.status_code < 400:
            result = r.url
        else:
            result = None
    except requests.RequestException:
        result = None
    ucache[url] = result
    return result


def coords_from_url(url):
    if not url:
        return None
    for pat in (r"@(-?\d+\.\d+),(-?\d+\.\d+)",          # google /@lat,lon
                r"[?&]ll=(-?\d+\.\d+),(-?\d+\.\d+)",     # ll=
                r"[?&]q=(-?\d+\.\d+),\+?(-?\d+\.\d+)",   # q=lat,lon
                r"/search/(-?\d+\.\d+),\+?(-?\d+\.\d+)", # search/lat,lon
                r"[?&]sll=(-?\d+\.\d+),(-?\d+\.\d+)",
                r"[?&]center=(-?\d+\.\d+),(-?\d+\.\d+)",
                r"[?&]destination=(-?\d+\.\d+),(-?\d+\.\d+)"):
        m = re.search(pat, url)
        if m:
            c = (float(m.group(1)), float(m.group(2)))
            if near_dallas(*c):
                return c
    # Bing:  cp=lat~lon
    m = re.search(r"[?&]cp=(-?\d+\.\d+)~(-?\d+\.\d+)", url)
    if m:
        c = (float(m.group(1)), float(m.group(2)))
        if near_dallas(*c):
            return c
    return None


def name_from_url(url):
    """Pull a geocodable place/address string out of a resolved map URL."""
    if not url:
        return None
    from urllib.parse import unquote, urlparse, parse_qs
    q = parse_qs(urlparse(url).query)
    for key in ("q", "query", "where1", "daddr", "destination", "address"):
        if key in q and q[key]:
            val = unquote(q[key][0]).replace("+", " ").strip()
            if val and not LATLON_RE.search(val):
                return val
    # google /maps/place/<Name+Address>/...  (skip the feature-id-only form)
    m = re.search(r"/maps/place/([^/@]+)", url)
    if m:
        val = unquote(m.group(1)).replace("+", " ").strip()
        if val and val not in ("", "data="):
            return val
    return None


# --------------------------------------------------------------------------
# Geocoding (rate-limited Nominatim)
# --------------------------------------------------------------------------
def make_geocoder():
    geo = Nominatim(user_agent=UA, timeout=10)
    return RateLimiter(geo.geocode, min_delay_seconds=1.1,
                       max_retries=2, error_wait_seconds=5.0,
                       swallow_exceptions=True)


def add_context(addr):
    """Give bare place names a fighting chance: add DFW context."""
    a = addr.strip()
    if re.search(r"\b7\d{4}\b", a):                 # has a TX zip already
        return a
    if re.search(r",\s*(TX|Texas)\b", a, re.IGNORECASE):
        return a
    if re.search(r"\b(dallas|worth|arlington|plano|irving|denton|frisco|"
                 r"carrollton|richardson|garland|euless|bedford|grapevine|"
                 r"mckinney|lewisville|addison|roanoke|keller|mesquite)\b",
                 a, re.IGNORECASE):
        return a + ", TX"
    return a + ", Dallas, TX"


def geo_candidates(addr):
    """Ordered geocode attempts, most specific to coarsest (zip centroid)."""
    cands = [add_context(addr), addr]
    # drop a leading business/place name: start at the first street number
    m = re.search(r"\d", addr)
    if m and m.start() > 0:
        cands.append(add_context(addr[m.start():]))
    # last resort: a TX zip anywhere -> zip centroid (approximate dot)
    z = re.search(r"\b(7\d{4})\b", addr)
    if z:
        cands.append(f"{z.group(1)}, TX")
    seen, out = set(), []
    for c in cands:
        c = c.strip(" ,-")
        if c and c not in seen:
            seen.add(c)
            out.append(c)
    return out


def geocode(geocode_fn, addr):
    if not addr:
        return None
    for candidate in geo_candidates(addr):
        try:
            loc = geocode_fn(candidate)
        except Exception:
            loc = None
        if loc and near_dallas(loc.latitude, loc.longitude):
            return (loc.latitude, loc.longitude)
    return None


# --------------------------------------------------------------------------
# Full per-event resolution
# --------------------------------------------------------------------------
def resolve(start_raw, map_raw, ccache, ucache, geocode_fn, retry_failed):
    start = clean_text(start_raw)
    mp = (map_raw or "").strip()
    key = ("URL::" + mp) if mp else ("ADDR::" + start)
    if not start and not mp:
        return None, "empty"
    if key in ccache and not (ccache[key] is None and retry_failed):
        v = ccache[key]
        return (tuple(v) if v else None), "cache"

    # 1. raw lat/lon or plus code sitting in the START text
    c = parse_latlon(start) or parse_pluscode(start)
    method = "latlon/plus" if c else None

    # 2. the map link
    if not c and mp and mp.upper() != "MAP" and mp.startswith(("http://", "https://")):
        url = expand_url(mp, ucache) if needs_expand(mp) else mp
        if url:
            c = coords_from_url(url)
            method = "url-coords" if c else None
            if not c:
                nm = name_from_url(url)
                if nm:
                    c = geocode(geocode_fn, clean_text(nm))
                    method = "url-name" if c else None

    # 3. fall back to geocoding the START address / place name
    if not c and start:
        c = geocode(geocode_fn, start)
        method = "geocode-start" if c else None

    ccache[key] = list(c) if c else None
    return c, (method or "failed")


# --------------------------------------------------------------------------
# Reading events
# --------------------------------------------------------------------------
def year_month(fname):
    m = re.match(r"(\d{4})-(\d{2})", os.path.basename(fname))
    return (m.group(1), m.group(2)) if m else (None, None)


def read_events(android_dir, cut_year, cut_month):
    events = []
    files = sorted(glob.glob(os.path.join(android_dir, "20*-*.txt")))
    for fp in files:
        y, mo = year_month(fp)
        if not y:
            continue
        if (y, mo) > (cut_year, cut_month):
            continue
        with open(fp, "r", encoding="utf-8", errors="replace") as f:
            r = csv.reader(f, delimiter="\t")
            next(r, None)
            for row in r:
                if len(row) < 9:
                    continue
                kennel = row[1].strip()
                if not kennel or kennel == "KENNEL":
                    continue
                title = clean_text(row[3])
                run = row[4].strip() if len(row) > 4 else ""
                start = row[7]
                mp = row[8]
                date = row[13].strip() if len(row) > 13 else f"{y}-{mo}"
                events.append(dict(year=y, kennel=kennel, title=title,
                                   run=run, start=start, mp=mp, date=date))
    return events


# --------------------------------------------------------------------------
# Map rendering
# --------------------------------------------------------------------------
def popup_html(ev, kc):
    parts = [f"<b>{html.escape(ev['date'])}</b>"]
    if ev["title"]:
        parts.append(html.escape(ev["title"]))
    line = ev["kennel"]
    if ev["run"]:
        line += f" #{ev['run']}"
    parts.append(html.escape(line))
    addr = clean_text(ev["start"])
    if addr:
        parts.append(html.escape(addr))
    return "<div style='font:12px/1.4 Arial'>" + "<br>".join(parts) + "</div>"


def render_map(events_with_coords, out_path, title, legend=None):
    pts = [(ev, c) for ev, c in events_with_coords if c]
    if not pts:
        print(f"  [skip] {os.path.basename(out_path)} - no points")
        return 0
    m = folium.Map(location=DALLAS, zoom_start=10, control_scale=True,
                   tiles="OpenStreetMap")
    for ev, c in pts:
        kc = classify(ev["kennel"])
        folium.CircleMarker(
            location=c, radius=5, weight=1,
            color="#333333" if kc["color"].lower() in ("#ffffff", "#fff") else kc["color"],
            fill=True, fill_color=kc["color"], fill_opacity=0.85,
            popup=folium.Popup(popup_html(ev, kc), max_width=280),
            tooltip=ev["kennel"],
        ).add_to(m)
    m.get_root().html.add_child(folium.Element(
        f"<h3 style='position:fixed;top:8px;left:60px;z-index:9999;"
        f"background:#fff;padding:4px 10px;border:2px solid #006843;"
        f"font-family:Arial;border-radius:4px'>{html.escape(title)}</h3>"))
    if legend:
        rows = "".join(
            f"<div><span style='display:inline-block;width:12px;height:12px;"
            f"background:{col};border:1px solid #333;margin-right:6px'></span>{lbl}</div>"
            for lbl, col in legend)
        m.get_root().html.add_child(folium.Element(
            f"<div style='position:fixed;bottom:20px;left:12px;z-index:9999;"
            f"background:#fff;padding:8px 10px;border:2px solid #006843;"
            f"font:12px Arial;border-radius:4px'>{rows}</div>"))
    m.save(out_path)
    print(f"  [ok]  {os.path.basename(out_path)} - {len(pts)} points")
    return len(pts)


def master_legend():
    seen, out = set(), []
    for k in KENNELS:
        if k["label"] in seen:
            continue
        seen.add(k["label"])
        out.append((k["label"], k["color"]))
    return out


# --------------------------------------------------------------------------
# Main
# --------------------------------------------------------------------------
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("android_dir")
    ap.add_argument("--out", required=True, help="Maps output directory")
    ap.add_argument("--cut", default="2026-07", help="Include events up to YYYY-MM")
    ap.add_argument("--retry-failed", action="store_true",
                    help="Re-attempt previously failed lookups")
    ap.add_argument("--resolve-only", action="store_true",
                    help="Only build the coord cache; don't render maps")
    args = ap.parse_args()

    cut_year, cut_month = args.cut.split("-")
    here = os.path.dirname(os.path.abspath(__file__))
    ccache = load_json(os.path.join(here, "coords_cache.json"))
    ucache = load_json(os.path.join(here, "url_cache.json"))
    # purge known-bad legacy cache entries (e.g. 'TBD' -> India)
    for bad in ("TBD",):
        ccache.pop(bad, None)

    events = read_events(args.android_dir, cut_year, cut_month)
    print(f"Read {len(events)} events through {args.cut}")

    geocode_fn = make_geocoder()
    stats = {}
    resolved = []
    t0 = time.time()
    for i, ev in enumerate(events):
        c, method = resolve(ev["start"], ev["mp"], ccache, ucache,
                            geocode_fn, args.retry_failed)
        stats[method] = stats.get(method, 0) + 1
        resolved.append((ev, c))
        if (i + 1) % 100 == 0:
            save_json(os.path.join(here, "coords_cache.json"), ccache)
            save_json(os.path.join(here, "url_cache.json"), ucache)
            print(f"  ...{i+1}/{len(events)}  ({time.time()-t0:.0f}s)  {stats}")
            sys.stdout.flush()
    save_json(os.path.join(here, "coords_cache.json"), ccache)
    save_json(os.path.join(here, "url_cache.json"), ucache)

    got = sum(1 for _, c in resolved if c)
    print(f"\nResolved {got}/{len(events)} events.  Methods: {stats}")

    if args.resolve_only:
        return

    os.makedirs(args.out, exist_ok=True)
    leg = master_legend()

    # Master
    render_map(resolved, os.path.join(args.out, f"allRuns_2012-{cut_year}.html"),
               f"All Hash Runs  Jun 2012 - {args.cut}", legend=leg)

    # Per year
    years = sorted({ev["year"] for ev, _ in resolved})
    for y in years:
        sub = [(ev, c) for ev, c in resolved if ev["year"] == y]
        render_map(sub, os.path.join(args.out, f"{y}.html"),
                   f"Hash Runs {y}", legend=leg)

    # Per kennel
    files = {}
    for ev, c in resolved:
        f = classify(ev["kennel"])["file"]
        if f:
            files.setdefault(f, []).append((ev, c))
    labels = {k["file"]: k["label"] for k in KENNELS if k["file"]}
    for fkey, sub in files.items():
        render_map(sub, os.path.join(args.out, f"{fkey}.html"),
                   f"{labels[fkey]}  Jun 2012 - {args.cut}")

    print("\nDone.")


if __name__ == "__main__":
    main()
