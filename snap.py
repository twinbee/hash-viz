#!/usr/bin/env python3
"""snap.py <maps_dir> - screenshot folium html maps to .jpg thumbnails via headless Chrome."""
import os, sys, subprocess, glob, tempfile

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
NAMES = ["allRuns", "2026", "dallas", "ftw", "duh", "noduh", "fullmoon", "bike", "dumb", "yak"]

def shot(html_path, png_path):
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars",
                    "--window-size=800,620", "--virtual-time-budget=9000",
                    f"--screenshot={png_path}", "file:///" + html_path.replace("\\", "/")],
                   check=False, timeout=60)

def main(maps_dir):
    try:
        from PIL import Image
        havepil = True
    except Exception:
        havepil = False
    for html_path in sorted(glob.glob(os.path.join(maps_dir, "*.html"))):
        base = os.path.basename(html_path)
        stem = base[:-5]
        # map allRuns_2012-2026 -> allRuns.jpg style name kept as-is otherwise
        out_stem = "allRuns_2012-2024" if stem.startswith("allRuns") else stem
        png = os.path.join(maps_dir, out_stem + ".png")
        shot(os.path.abspath(html_path), png)
        if os.path.exists(png) and havepil:
            jpg = os.path.join(maps_dir, out_stem + ".jpg")
            im = Image.open(png).convert("RGB")
            im.save(jpg, "JPEG", quality=82)
            os.remove(png)
            print("thumb:", os.path.basename(jpg))
        else:
            print("png only / no PIL:", os.path.basename(png), os.path.exists(png))

if __name__ == "__main__":
    main(sys.argv[1])
