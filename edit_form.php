<form method="POST" action="" id="editForm" enctype="multipart/form-data" onsubmit="return allowLeave();">
	<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
	
	<?php if ($isNewEvent): ?>
	<div class="form-group">
		<label>Date:</label>
		<div class="date-selectors">
			<select name="month" id="monthSelect">
				<?php for ($m = 1; $m <= 12; $m++): ?>
				<option value="<?php echo $m; ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
					<?php echo date('F', mktime(0, 0, 0, $m, 1, $year)); ?>
				</option>
				<?php endfor; ?>
			</select>
			<select name="day" id="daySelect">
				<?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
				<option value="<?php echo $d; ?>" <?php echo ($d == $day) ? 'selected' : ''; ?>><?php echo $d; ?></option>
				<?php endfor; ?>
			</select>
			<select name="year" id="yearSelect">
				<?php $currentYear = date('Y'); for ($y = $currentYear; $y <= 2050; $y++): ?>
				<option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
				<?php endfor; ?>
			</select>
		</div>
		<small style="color: #666;">You can create events from <?php echo $currentYear; ?> through 2050.</small>
		<div id="datePreview" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
			<strong id="dayOfWeek"></strong>
			<span id="otherEvents"></span>
		</div>
	</div>
	<?php endif; ?>
	
	<div class="form-group">
		<label>Kennel:</label>
		<select name="kennel" id="kennelSelect" onchange="updateKennelSelection()" required>
			<option value="">-- Select Kennel --</option>
			<?php 
			$kennelList = getKennelList($month, $year);
			$currentKennel = isset($data[1]) ? trim($data[1]) : '';
			$kennelFound = false;
			foreach ($kennelList as $kennelName => $defaultIcon): 
				$selected = ($currentKennel == $kennelName) ? 'selected' : '';
				if ($selected) $kennelFound = true;
			?>
			<option value="<?php echo htmlspecialchars($kennelName); ?>" data-icon="<?php echo htmlspecialchars($defaultIcon); ?>" <?php echo $selected; ?>>
				<?php echo htmlspecialchars($kennelName); ?>
			</option>
			<?php endforeach; ?>
			<?php if (!$kennelFound && !empty($currentKennel)): ?>
			<option value="<?php echo htmlspecialchars($currentKennel); ?>" selected><?php echo htmlspecialchars($currentKennel); ?> (not in defaults)</option>
			<?php endif; ?>
		</select>
		<div style="margin-top: 5px;">
			<a href="kenneldefaults.php" style="font-size: 13px;">➕ Add new kennel or edit defaults</a>
		</div>
	</div>
	
	<div class="form-group">
		<label>Icon:</label>
		<select name="type" id="iconSelect" onchange="updateIconPreview()">
			<option value="">-- Select Icon --</option>
			<?php foreach ($availableIcons as $icon): ?>
			<option value="<?php echo htmlspecialchars($icon); ?>" <?php echo ($currentIcon == $icon || (isset($uploadedIcon) && $uploadedIcon == $icon)) ? 'selected' : ''; ?>>
				<?php echo htmlspecialchars($icon); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<img id="iconPreview" class="icon-preview" src="<?php echo !empty($currentIcon) ? htmlspecialchars($year . '/' . $currentIcon) : ''; ?>" style="<?php echo empty($currentIcon) ? 'display:none;' : ''; ?>" data-year="<?php echo $year; ?>">
		<div style="margin-top: 10px;">
			<label style="display: inline-block; padding: 8px 15px; background: #6c757d; color: white; border-radius: 5px; cursor: pointer; font-size: 14px;">
				📤 Upload Icon...
				<input type="file" name="icon_upload" accept=".png,.jpg,.jpeg,.svg" style="display: none;" onchange="this.form.submit();">
			</label>
			<small style="display: block; color: #666; margin-top: 5px;">PNG, JPG, or SVG. Max 200x150px, 500KB. Will auto-resize if needed.</small>
		</div>
		<?php if (empty($availableIcons)): ?>
		<small style="color: #999;">No icon files found in <?php echo $year; ?>/ folder.</small>
		<?php endif; ?>
	</div>
	
	<div class="form-group">
		<label>Title:</label>
		<input type="text" name="title" value="<?php echo htmlspecialchars($data[3]); ?>">
	</div>
	
	<div class="form-group">
		<label>Run Number:</label>
		<input type="text" name="run" value="<?php echo htmlspecialchars($data[4]); ?>">
	</div>
	
	<div class="form-group">
		<label>Hares:</label>
		<input type="text" name="hares" value="<?php echo htmlspecialchars($data[5]); ?>">
	</div>
	
	<div class="form-group">
		<label>Time: <span style="color: red;">*</span></label>
		<input type="text" name="time" value="<?php echo htmlspecialchars(isset($data[6]) && !empty($data[6]) ? $data[6] : '7:00 PM'); ?>" placeholder="7:00 PM" required>
		<small style="color: #666;">Must start with "H:MM AM" or "H:MM PM" (e.g., "7:00 PM" or "2:00 PM Trail starts"). <strong>Required</strong> for EditHash 1.28 compatibility.</small>
	</div>
	
	<div class="form-group">
		<label>Address:</label>
		<textarea name="address" id="addressField"><?php 
			$addr = isset($data[7]) ? $data[7] : '';
			$addr = str_replace("<br />", "\n", $addr);
			$addr = str_replace("<br/>", "\n", $addr);
			$addr = str_replace("<br>", "\n", $addr);
			echo htmlspecialchars($addr); 
		?></textarea>
	</div>
	
	<div class="form-group">
		<label>Map Link:</label>
		<div style="display: flex; gap: 10px; align-items: center;">
			<input type="text" name="maplink" id="maplinkInput" value="<?php echo htmlspecialchars(isset($data[8]) ? $data[8] : ''); ?>" style="flex: 1;">
			<button type="button" onclick="autoGenMapLink()" class="btn" style="background: #28a745; color: white; padding: 8px 15px; white-space: nowrap;">🗺️ AutoGen</button>
		</div>
		<small style="color: #666;">Click AutoGen to create a Google Maps link from the address above.</small>
	</div>
	
	<div class="form-group">
		<label>Hash Cash:</label>
		<input type="text" name="hashcash" value="<?php echo htmlspecialchars(isset($data[9]) ? $data[9] : ''); ?>">
	</div>
	
	<div class="form-group">
		<label>TURDs? (Dogs):</label>
		<select name="turds">
			<option value="" <?php echo ($currentTurds == '') ? 'selected' : ''; ?>>-- Select --</option>
			<option value="Yes" <?php echo ($currentTurds == 'Yes') ? 'selected' : ''; ?>>Yes</option>
			<option value="No" <?php echo ($currentTurds == 'No') ? 'selected' : ''; ?>>No</option>
			<option value="Trail-Only" <?php echo ($currentTurds == 'Trail-Only') ? 'selected' : ''; ?>>Trail-Only</option>
		</select>
	</div>
	
	<div class="form-group">
		<label>Description:</label>
		<textarea name="desc" rows="10" placeholder="What do the hounds need to know about this trail?"><?php 
			$desc = isset($data[14]) ? $data[14] : '';
			$desc = preg_replace('/<!-- WEATHER_START -->.*?<!-- WEATHER_END -->/s', '', $desc);
			$desc = preg_replace('/Bring:\s*[^\n<]+\s*/i', '', $desc);
			$desc = preg_replace('/Trail Type:\s*(A to A\'?|A to B)\s*/i', '', $desc);
			$desc = str_replace("<br />", "\n", $desc);
			$desc = str_replace("<br/>", "\n", $desc);
			$desc = str_replace("<br>", "\n", $desc);
			$desc = trim($desc);
			echo htmlspecialchars($desc); 
		?></textarea>
		<small style="color: #666;">Note: Weather forecast, edit link, and calendar invite are added automatically.</small>
	</div>
	
	<div class="form-group">
		<label>Trail Type:</label>
		<?php
		$currentTrailType = '';
		$descText = isset($data[14]) ? $data[14] : '';
		if (preg_match('/Trail Type:\s*(A to A\'?|A to B)/i', $descText, $trailMatch)) {
			$currentTrailType = trim($trailMatch[1]);
		}
		?>
		<select name="trailtype">
			<option value="" <?php echo ($currentTrailType == '') ? 'selected' : ''; ?>>-- Select --</option>
			<option value="A to A" <?php echo ($currentTrailType == 'A to A') ? 'selected' : ''; ?>>A to A</option>
			<option value="A to A'" <?php echo ($currentTrailType == "A to A'") ? 'selected' : ''; ?>>A to A'</option>
			<option value="A to B" <?php echo ($currentTrailType == 'A to B') ? 'selected' : ''; ?>>A to B</option>
		</select>
	</div>
	
	<div class="form-group">
		<label class="checkbox-label" style="display: inline-flex; align-items: center; gap: 8px;">
			<input type="checkbox" name="rsvp_enabled" value="1" <?php echo ($currentRsvp == '1') ? 'checked' : ''; ?>>
			<span>✅ Allow early check-in (RSVP)</span>
		</label>
		<small style="display: block; color: #666; margin-top: 5px;">Removes time restriction so hashers can check in early as an RSVP.</small>
	</div>
	
	<div class="form-group">
		<label>Bring:</label>
		<div class="checkbox-grid">
			<?php
			$bringOptions = array(
				'Flashlight', 'Extra Shoes', 'Extra Clothes', 'Anti-Shiggy',
				'Glowsticks', 'Virgins', 'On-In $', 'Bug Spray',
				'Swimsuit', 'Birthday Suit', 'DART', 'Pre-lube',
				'Leash', 'Trash Bags', 'BYOB', 'BYOE',
				'Vessel', 'Bowl/Spoon', 'Cash', 'Side Dish', 'Chair'
			);
			
			$existingBring = array();
			$descText = isset($data[14]) ? $data[14] : '';
			if (preg_match('/Bring:\s*([^<\n]+)/i', $descText, $bringMatch)) {
				$existingBring = array_map('trim', explode(',', $bringMatch[1]));
			}
			
			foreach ($bringOptions as $option):
				$checkboxId = 'bring_' . strtolower(str_replace(' ', '_', $option));
				$isChecked = in_array($option, $existingBring) ? 'checked' : '';
			?>
			<label class="checkbox-label">
				<input type="checkbox" name="bring[]" value="<?php echo htmlspecialchars($option); ?>" <?php echo $isChecked; ?>>
				<?php echo htmlspecialchars($option); ?>
			</label>
			<?php endforeach; ?>
		</div>
	</div>
	
	<div class="form-group">
		<button type="submit" name="save" class="btn btn-save">💾 <?php echo $isNewEvent ? 'Create Event' : 'Save Changes'; ?></button>
		<?php if ($isNewEvent): ?>
		<a href="/calendar" class="btn btn-cancel">❌ Cancel</a>
		<?php else: ?>
		<a href="<?php echo $year; ?>/event.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&no=<?php echo $no; ?>" class="btn btn-cancel">❌ Cancel</a>
		<?php endif; ?>
	</div>
</form>

<?php if (!$isNewEvent): ?>
<div class="action-buttons">
	<a href="edit.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>&action=new" class="btn btn-new">➕ Add New Event on This Day</a>
</div>

<div class="delete-section">
	<h4>⚠️ Danger Zone</h4>
	<p>Permanently delete this event. A backup will be created before deletion.</p>
	<form method="POST" action="" onsubmit="formChanged = false; return confirmDelete();">
		<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
		<button type="submit" name="delete" class="btn btn-delete">🗑️ Delete Event</button>
	</form>
	<br>
	<p>Create a copy of this event with today's date and no run number.</p>
	<form method="GET" action="edit.php" style="display: inline;">
		<input type="hidden" name="action" value="duplicate">
		<input type="hidden" name="source_year" value="<?php echo $year; ?>">
		<input type="hidden" name="source_month" value="<?php echo $month; ?>">
		<input type="hidden" name="source_day" value="<?php echo $day; ?>">
		<input type="hidden" name="source_no" value="<?php echo $no; ?>">
		<button type="submit" class="btn btn-duplicate">📋 Duplicate Event</button>
	</form>
	<br>
	<p>Restore a previously deleted event from backup.</p>
	<form method="GET" action="recover.php" onsubmit="return confirmRecovery();" style="display: inline;">
		<button type="submit" class="btn btn-recover">🔄 Event Recovery</button>
	</form>
</div>

<p><small>Last updated: <?php echo isset($data[15]) ? htmlspecialchars($data[15]) : 'N/A'; ?></small></p>
<?php endif; ?>