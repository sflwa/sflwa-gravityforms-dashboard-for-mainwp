<?php
/**
 * Admin Dashboard View - Interactive Executive Table with MainWP Launch Links & Cron Indicator
 *
 * @package MainWP_Gravity_Forms_Triage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db_engine            = MainWP_GF_Triage_DB::instance();
$total_sites          = $db_engine->get_logged_sites_count();
$total_forms          = $db_engine->get_logged_forms_count();
$clients_list         = $db_engine->get_unique_clients();
$fleet_data           = $db_engine->get_fleet_accordion_data();
$ajax_nonce           = wp_create_nonce( 'mainwp_gf_triage_nonce' );
$mainwp_open_nonce    = wp_create_nonce( 'mainwp-admin-nonce' );

$m1_label = date( 'F Y', strtotime( 'first day of previous month' ) );
$m2_label = date( 'F Y', strtotime( 'first day of -2 months' ) );

// Fetch Configurable Tier Values
$low_max   = get_option( 'mwp_gf_low_vol_max', 25 );
$low_drop  = get_option( 'mwp_gf_low_vol_drop', 80 );
$med_max   = get_option( 'mwp_gf_med_vol_max', 100 );
$med_drop  = get_option( 'mwp_gf_med_vol_drop', 50 );
$high_drop = get_option( 'mwp_gf_high_vol_drop', 25 );

// Fleet Milestones Analytics
$alert_count    = 0;
$zero_sub_count = 0;
$top_site_name  = 'N/A';
$top_site_val   = 0;
$top_form_name  = 'N/A';
$top_form_val   = 0;

if ( ! empty( $fleet_data ) && is_array( $fleet_data ) ) {
	foreach ( $fleet_data as $s ) {
		if ( ! empty( $s['is_concern'] ) ) {
			$alert_count++;
		}
		
		if ( 0 === (int) $s['m1_active'] ) {
			$zero_sub_count++;
		}

		if ( (int) $s['m1_active'] > $top_site_val ) {
			$top_site_val  = (int) $s['m1_active'];
			$top_site_name = $s['site_name'];
		}

		if ( ! empty( $s['forms'] ) ) {
			foreach ( $s['forms'] as $f ) {
				if ( (int) $f['m1_act'] > $top_form_val ) {
					$top_form_val  = (int) $f['m1_act'];
					$top_form_name = $f['form_title'] . ' (' . $s['site_name'] . ')';
				}
			}
		}
	}
}

// Next Cron Execution Schedule Calculation
$next_cron_timestamp = wp_next_scheduled( 'mainwp_gf_triage_monthly_cron_hook' );
$next_cron_display   = 'Not Scheduled';
if ( $next_cron_timestamp ) {
	$time_diff         = human_time_diff( current_time( 'timestamp' ), $next_cron_timestamp );
	$formatted_date    = wp_date( 'F j, Y \a\t g:i A', $next_cron_timestamp );
	$next_cron_display = sprintf( '%s (in %s)', $formatted_date, $time_diff );
}
?>

<style>
	.mwp-gf-wrap { max-width: 100%; margin: 20px 20px 0 0; }
	.mwp-gf-metrics { display: flex; gap: 20px; margin-bottom: 20px; }
	.mwp-gf-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
	.mwp-gf-metric-card { flex: 1; text-align: center; }
	.mwp-gf-metric-card h3 { margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; color: #646970; }
	.mwp-gf-metric-val { font-size: 28px; font-weight: 700; line-height: 1.2; }

	/* Milestone Highlights Bar */
	.mwp-gf-milestone-bar { display: flex; gap: 15px; margin-bottom: 20px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px 18px; }
	.mwp-gf-milestone-item { flex: 1; font-size: 12px; color: #334155; }
	.mwp-gf-milestone-item strong { color: #0f172a; }

	/* Sortable Column Headers */
	.mwp-gf-sortable { cursor: pointer; user-select: none; position: relative; padding-right: 18px !important; }
	.mwp-gf-sortable:hover { background-color: #f1f5f9 !important; }
	.mwp-gf-sortable::after { content: '↕'; position: absolute; right: 5px; color: #94a3b8; font-size: 11px; }
	.mwp-gf-sortable.sort-asc::after { content: '↑'; color: #2563eb; }
	.mwp-gf-sortable.sort-desc::after { content: '↓'; color: #2563eb; }

	/* Row Highlighting */
	.mwp-gf-row-alert { background-color: #fef2f2 !important; border-left: 4px solid #ef4444 !important; }
	.mwp-gf-row-alert:hover { background-color: #fee2e2 !important; }

	/* Badges & Links */
	.mwp-gf-site-link { color: #1d2327; text-decoration: none; font-weight: 700; }
	.mwp-gf-site-link:hover { color: #2271b1; text-decoration: underline; }
	.mwp-gf-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
	.mwp-gf-badge-blue { background: #e0f2fe; color: #0369a1; }
	.mwp-gf-badge-green { background: #dcfce7; color: #15803d; }
	.mwp-gf-badge-red { background: #fee2e2; color: #b91c1c; }

	/* Accordion Drawers */
	.mwp-gf-drawer-row { display: none; background: #f8fafc; }
	.mwp-gf-drawer-content { padding: 15px; border-bottom: 2px solid #e2e8f0; }

	.mwp-gf-form-accordion { margin-bottom: 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; }
	.mwp-gf-form-summary { padding: 10px 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 13px; }
	.mwp-gf-form-body { padding: 12px; border-top: 1px solid #cbd5e1; display: none; background: #fff; }

	.mwp-gf-console { background: #111827; color: #10b981; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; line-height: 1.6; max-height: 300px; overflow-y: auto; }
</style>

<div class="wrap mwp-gf-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-feedback" style="font-size: 30px; width: 30px; height: 30px; margin-right: 8px; vertical-align: middle;"></span>
		MainWP Gravity Forms Triage Status Board
	</h1>
	<p style="color: #646970; margin-top: 2px;">Developed by <strong>South Florida Web Advisors</strong> (SFLWA)</p>

	<hr class="wp-header-end">

	<!-- Metric Header Cards -->
	<div class="mwp-gf-metrics">
		<div class="mwp-gf-card mwp-gf-metric-card">
			<h3>Logged Child Sites</h3>
			<div id="stat_sites_cnt" class="mwp-gf-metric-val" style="color: #2563eb;"><?php echo esc_html( $total_sites ); ?></div>
		</div>
		<div class="mwp-gf-card mwp-gf-metric-card">
			<h3>Monitored Forms</h3>
			<div id="stat_forms_cnt" class="mwp-gf-metric-val" style="color: #059669;"><?php echo esc_html( $total_forms ); ?></div>
		</div>
		<div class="mwp-gf-card mwp-gf-metric-card">
			<h3>Active Churn Alerts</h3>
			<div class="mwp-gf-metric-val" style="color: <?php echo $alert_count > 0 ? '#dc2626' : '#2563eb'; ?>;">
				<?php echo esc_html( $alert_count ); ?>
			</div>
		</div>
	</div>

	<!-- Fleet Milestones Highlight Bar -->
	<div class="mwp-gf-milestone-bar">
		<div class="mwp-gf-milestone-item">
			🏆 <strong>Top Submitting Site (<?php echo esc_html( date( 'M Y', strtotime( 'first day of previous month' ) ) ); ?>):</strong><br>
			<?php echo esc_html( $top_site_name ); ?> (<strong><?php echo esc_html( number_format( $top_site_val ) ); ?></strong> entries)
		</div>
		<div class="mwp-gf-milestone-item">
			⭐ <strong>Top Performing Form:</strong><br>
			<?php echo esc_html( $top_form_name ); ?> (<strong><?php echo esc_html( number_format( $top_form_val ) ); ?></strong> entries)
		</div>
		<div class="mwp-gf-milestone-item">
			💤 <strong>Zero Entry Sites (<?php echo esc_html( date( 'M Y', strtotime( 'first day of previous month' ) ) ); ?>):</strong><br>
			<strong><?php echo esc_html( $zero_sub_count ); ?></strong> sites recorded 0 active submissions
		</div>
		<div class="mwp-gf-milestone-item" style="text-align: right;">
			⏱ <strong>Next Scheduled Auto-Sync:</strong><br>
			<span style="color: #2563eb; font-weight: 600;"><?php echo esc_html( $next_cron_display ); ?></span>
		</div>
	</div>

	<!-- Controls & Filter Bar -->
	<div class="mwp-gf-card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
		<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
			<!-- Live Site Name Search Filter -->
			<div>
				<label for="table_search_input"><strong>Search Site Name:</strong></label>
				<input type="text" id="table_search_input" onkeyup="applyBoardFilters()" placeholder="Type to filter..." style="margin-left: 6px; padding: 3px 8px; width: 200px;">
			</div>

			<!-- Client Filter Dropdown -->
			<div>
				<label for="filter_client_id"><strong>Client Filter:</strong></label>
				<select id="filter_client_id" onchange="applyBoardFilters()" style="margin-left: 6px;">
					<option value="">-- All MainWP Clients --</option>
					<?php foreach ( $clients_list as $c ) : ?>
						<option value="<?php echo esc_attr( $c['client_id'] ); ?>">
							<?php echo esc_html( $c['client_name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Risk Level Filter -->
			<div>
				<label for="filter_risk_level"><strong>Filter Risk:</strong></label>
				<select id="filter_risk_level" onchange="applyBoardFilters()" style="margin-left: 6px;">
					<option value="all">All Sites</option>
					<option value="alerts">🔴 Action Alerts Only</option>
					<option value="low">Low Vol (&lt; <?php echo esc_html( $low_max ); ?>/mo)</option>
					<option value="medium">Med Vol (<?php echo esc_html( $low_max ); ?>-<?php echo esc_html($med_max ); ?>/mo)</option>
					<option value="high">High Vol (&gt; <?php echo esc_html( $med_max ); ?>/mo)</option>
				</select>
			</div>
		</div>

		<div style="display: flex; gap: 10px;">
			<button type="button" class="button button-secondary" onclick="toggleSettingsModal(true)">
				⚙ Threshold Settings
			</button>
			<button type="button" class="button button-primary" id="btn_start_sync" onclick="startFleetSync()">
				🔄 Sync Network Data (Full Fleet)
			</button>
		</div>
	</div>

	<!-- Network Sync Console Drawer -->
	<div id="sync_console_wrapper" class="mwp-gf-card" style="display: none; margin-bottom: 20px; background: #111827;">
		<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #374151; padding-bottom: 8px; margin-bottom: 10px;">
			<strong style="color: #f3f4f6;">Fleet Synchronization Log</strong>
			<div>
				<span id="sync_progress_counter" style="color: #6ee7b7; font-weight: bold; margin-right: 15px;">0 / 0 Sites</span>
				<button type="button" class="button button-small button-secondary" id="btn_reload_page" onclick="location.reload()" style="display: none;">
					🔄 Reload Dashboard View
				</button>
			</div>
		</div>
		<div id="sync_console_log" class="mwp-gf-console">Awaiting sync execution...</div>
	</div>

	<!-- EXECUTIVE TRIAGE TABLE BOARD -->
	<div class="mwp-gf-card" style="padding: 0; overflow: hidden;">
		<?php if ( empty( $fleet_data ) ) : ?>
			<div style="padding: 20px; color: #64748b;">No cached data available. Click <strong>"Sync Network Data"</strong> above.</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" id="main_triage_table">
				<thead>
					<tr>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(0, 'string')" style="width: 22%;">Site Name</th>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(1, 'string')" style="width: 15%;">Client Name</th>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(2, 'string')" style="width: 10%;">Traffic Tier</th>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(3, 'num')" style="width: 10%;">3-Mo Avg</th>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(4, 'num')" style="width: 10%;"><?php echo esc_html( $m1_label ); ?></th>
						<th class="mwp-gf-sortable" onclick="sortTableByColumn(5, 'num')" style="width: 11%;">vs 3-Mo Avg</th>
						<th style="width: 17%;">Diagnostics / "Why"</th>
						<th style="width: 5%; text-align: right;">Action</th>
					</tr>
				</thead>
				<tbody id="triage_tbody">
					<?php foreach ( $fleet_data as$site ) : 
						$is_alert  = ! empty( $site['is_concern'] );
						$row_class =$is_alert ? 'mwp-gf-row-alert' : '';
						$pct_class = $site['vs_avg_change_pct'] < 0 ? 'mwp-gf-badge-red' : 'mwp-gf-badge-green';$open_url  = admin_url( 'admin.php?page=SiteOpen&newWindow=yes&websiteid=' . absint( $site['site_id'] ) . '&_opennonce=' . esc_attr( $mainwp_open_nonce ) );
					?>
						<tr class="site-main-row <?php echo esc_attr( $row_class ); ?>" 
						    data-client-id="<?php echo esc_attr( $site['client_id'] ); ?>"
						    data-risk="<?php echo $is_alert ? 'alerts' : 'stable'; ?>"
						    data-tier="<?php echo esc_attr( $site['traffic_tier'] ); ?>">
							
							<td>
								<a href="<?php echo esc_url( $open_url ); ?>" target="_blank" class="mwp-gf-site-link" title="Open Site Admin in MainWP">
									<?php echo esc_html( $site['site_name'] ); ?> ↗
								</a>
							</td>
							<td><?php echo esc_html( $site['client_name'] ); ?></td>
							<td>
								<span class="mwp-gf-badge mwp-gf-badge-blue" style="text-transform: uppercase;">
									<?php echo esc_html( $site['traffic_tier'] ); ?>
								</span>
							</td>
							<td data-sort="<?php echo esc_attr( $site['rolling_avg_3m'] ); ?>">
								<code><?php echo esc_html( $site['rolling_avg_3m'] ); ?>/mo</code>
							</td>
							<td data-sort="<?php echo esc_attr( $site['m1_active'] ); ?>">
								<strong style="color: #15803d;"><?php echo esc_html( $site['m1_active'] ); ?></strong>
							</td>
							<td data-sort="<?php echo esc_attr( $site['vs_avg_change_pct'] ); ?>">
								<span class="mwp-gf-badge <?php echo esc_attr( $pct_class ); ?>">
									<?php echo esc_html( $site['vs_avg_change_pct'] ); ?>%
								</span>
							</td>
							<td style="font-size: 12px; color: <?php echo $is_alert ? '#b91c1c' : '#475569'; ?>; font-weight: <?php echo $is_alert ? '600' : 'normal'; ?>;">
								<?php echo esc_html( $site['why_diagnostic'] ); ?>
							</td>
							<td style="text-align: right;">
								<button type="button" class="button button-small" onclick="toggleSiteDrawer(<?php echo esc_attr( $site['site_id'] ); ?>)">
									Forms ▾
								</button>
							</td>
						</tr>

						<!-- Inline Accordion Drawer Row -->
						<tr class="mwp-gf-drawer-row site-drawer-row" id="drawer_row_<?php echo esc_attr( $site['site_id'] ); ?>" data-client-id="<?php echo esc_attr( $site['client_id'] ); ?>">
							<td colspan="8" style="padding: 0;">
								<div class="mwp-gf-drawer-content">
									<h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #334155;">
										Form Breakdown for <?php echo esc_html( $site['site_name'] ); ?>
										<a href="<?php echo esc_url( $open_url ); ?>" target="_blank" style="margin-left: 8px; font-weight: normal; font-size: 11px;">(Open Site Admin ↗)</a>
									</h4>
									
									<?php if ( empty( $site['forms'] ) ) : ?>
										<p style="color: #64748b; font-size: 12px; margin: 0;">No forms found on this site.</p>
									<?php else : ?>
										<?php foreach ( $site['forms'] as$form ) : ?>
											<div class="mwp-gf-form-accordion">
												<div class="mwp-gf-form-summary" onclick="toggleFormBody(this)">
													<div>
														<strong style="color: #0f172a;"><?php echo esc_html( $form['form_title'] ); ?></strong>
														<span style="font-size: 11px; color: #64748b; margin-left: 8px;">(Form ID: <?php echo esc_html( $form['form_id'] ); ?>)</span>
													</div>
													<div style="display: flex; gap: 12px; align-items: center;">
														<span style="font-size: 11px; color: #334155;">
															Latest: <strong><?php echo esc_html( $form['m1_act'] ); ?></strong> | 
															3-Mo Avg: <strong><?php echo esc_html($form['form_rolling_avg'] ); ?></strong>
														</span>
														<span style="font-size: 11px; color: #b91c1c; font-weight: bold;">
															Spam: <?php echo esc_html( $form['total_spam'] ); ?>
														</span>
														<span class="dashicons dashicons-arrow-down-alt2"></span>
													</div>
												</div>

												<div class="mwp-gf-form-body">
													<table class="wp-list-table widefat fixed striped" style="border: none;">
														<thead>
															<tr>
																<th>Month / Year</th>
																<th>Active Submissions</th>
																<th>Spam Entries</th>
															</tr>
														</thead>
														<tbody>
															<?php foreach ( $form['monthly_breakdown'] as$m ) : ?>
																<tr>
																	<td><code><?php echo esc_html( $m['month_year'] ); ?></code></td>
																	<td><strong style="color: #15803d;"><?php echo esc_html( $m['active_count'] ); ?></strong></td>
																	<td><span style="color: #b91c1c;"><?php echo esc_html( $m['spam_count'] ); ?></span></td>
																</tr>
															<?php endforeach; ?>
														</tbody>
													</table>
												</div>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Threshold Settings Modal -->
<div id="settings_modal" style="display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
	<div style="background: #fff; max-width: 500px; margin: 80px auto; padding: 25px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
		<h2 style="margin-top: 0;">⚙ Configurable Volume & Alert Settings</h2>
		<p style="color: #646970; font-size: 13px;">Fine-tune volume thresholds and percentage drop sensitivities for each traffic tier.</p>
		
		<form method="post" action="options.php">
			<?php settings_fields( 'mwp_gf_triage_settings' ); ?>
			
			<table class="form-table" style="margin-bottom: 20px;">
				<tr>
					<th>Low Volume Max</th>
					<td><input type="number" name="mwp_gf_low_vol_max" value="<?php echo esc_attr( $low_max ); ?>" class="small-text"> /mo</td>
				</tr>
				<tr>
					<th>Low Volume Drop Trigger</th>
					<td><input type="number" name="mwp_gf_low_vol_drop" value="<?php echo esc_attr( $low_drop ); ?>" class="small-text"> % drop</td>
				</tr>
				<tr>
					<th>Medium Volume Max</th>
					<td><input type="number" name="mwp_gf_med_vol_max" value="<?php echo esc_attr( $med_max ); ?>" class="small-text"> /mo</td>
				</tr>
				<tr>
					<th>Medium Volume Drop Trigger</th>
					<td><input type="number" name="mwp_gf_med_vol_drop" value="<?php echo esc_attr( $med_drop ); ?>" class="small-text"> % drop</td>
				</tr>
				<tr>
					<th>High Volume Drop Trigger</th>
					<td><input type="number" name="mwp_gf_high_vol_drop" value="<?php echo esc_attr( $high_drop ); ?>" class="small-text"> % drop</td>
				</tr>
			</table>

			<div style="text-align: right;">
				<button type="button" class="button button-secondary" onclick="toggleSettingsModal(false)">Cancel</button>
				<?php submit_button( 'Save Thresholds', 'primary', 'submit', false ); ?>
			</div>
		</form>
	</div>
</div>

<script>
let sortDirections = {};

function sortTableByColumn(colIndex, type) {
	const tbody = document.getElementById('triage_tbody');
	const headers = document.querySelectorAll('.mwp-gf-sortable');
	
	let isAsc = !sortDirections[colIndex];
	sortDirections[colIndex] = isAsc;

	headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
	headers[colIndex].classList.add(isAsc ? 'sort-asc' : 'sort-desc');

	let rows = [];
	const mainRows = Array.from(tbody.querySelectorAll('.site-main-row'));

	mainRows.forEach(mainRow => {
		const siteId = mainRow.querySelector('button').getAttribute('onclick').match(/\d+/)[0];
		const drawerRow = document.getElementById('drawer_row_' + siteId);
		rows.push({ main: mainRow, drawer: drawerRow });
	});

	rows.sort((a, b) => {
		let cellA = a.main.children[colIndex];
		let cellB = b.main.children[colIndex];

		let valA = cellA.getAttribute('data-sort') || cellA.innerText.trim();
		let valB = cellB.getAttribute('data-sort') || cellB.innerText.trim();

		if (type === 'num') {
			valA = parseFloat(valA) || 0;
			valB = parseFloat(valB) || 0;
			return isAsc ? valA - valB : valB - valA;
		} else {
			return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
		}
	});

	rows.forEach(pair => {
		tbody.appendChild(pair.main);
		if (pair.drawer) { tbody.appendChild(pair.drawer); }
	});
}

function toggleSettingsModal(show) {
	document.getElementById('settings_modal').style.display = show ? 'block' : 'none';
}

function toggleSiteDrawer(siteId) {
	const drawer = document.getElementById('drawer_row_' + siteId);
	if (drawer) {
		drawer.style.display = drawer.style.display === 'table-row' ? 'none' : 'table-row';
	}
}

function toggleFormBody(headerElem) {
	const body = headerElem.nextElementSibling;
	body.style.display = body.style.display === 'block' ? 'none' : 'block';
}

function applyBoardFilters() {
	const searchText = document.getElementById('table_search_input').value.toLowerCase().trim();
	const selectedClient = document.getElementById('filter_client_id').value;
	const selectedRisk = document.getElementById('filter_risk_level').value;

	const mainRows = document.querySelectorAll('.site-main-row');

	mainRows.forEach(function(row) {
		const rowText = row.innerText.toLowerCase();
		const clientId = row.getAttribute('data-client-id');
		const riskState = row.getAttribute('data-risk');
		const tierState = row.getAttribute('data-tier');
		const siteId = row.querySelector('button').getAttribute('onclick').match(/\d+/)[0];
		const drawerRow = document.getElementById('drawer_row_' + siteId);

		let matchSearch = !searchText || rowText.includes(searchText);
		let matchClient = !selectedClient || clientId === selectedClient;
		let matchRisk = true;

		if (selectedRisk === 'alerts') {
			matchRisk = riskState === 'alerts';
		} else if (selectedRisk === 'low' || selectedRisk === 'medium' || selectedRisk === 'high') {
			matchRisk = tierState === selectedRisk;
		}

		if (matchSearch && matchClient && matchRisk) {
			row.style.display = '';
		} else {
			row.style.display = 'none';
			if (drawerRow) { drawerRow.style.display = 'none'; }
		}
	});
}

function startFleetSync(offset = 0) {
	const btnSync = document.getElementById('btn_start_sync');
	const wrapper = document.getElementById('sync_console_wrapper');
	const logBox = document.getElementById('sync_console_log');
	const counter = document.getElementById('sync_progress_counter');
	const btnReload = document.getElementById('btn_reload_page');

	if (offset === 0) {
		btnSync.disabled = true;
		btnReload.style.display = 'none';
		wrapper.style.display = 'block';
		logBox.innerHTML = '<div>[INFO] Enqueuing non-blocking network query...</div>';
	}

	jQuery.post(ajaxurl, {
		action: 'mainwp_gf_triage_batch_sync',
		nonce: '<?php echo esc_js( $ajax_nonce ); ?>',
		offset: offset
	}, function(response) {
		if (response.success && response.data) {
			const data = response.data;

			if (data.site_logs && data.site_logs.length > 0) {
				data.site_logs.forEach(function(item) {
					const statusColor = item.status === 'success' ? '#10b981' : '#ef4444';
					logBox.innerHTML += `<div>[ID ${item.site_id}] <strong>${item.site_name}</strong>: <span style="color:${statusColor}">${item.message}</span></div>`;
				});
				logBox.scrollTop = logBox.scrollHeight;
			}

			counter.innerText = `${data.next_offset > data.total ? data.total : data.next_offset} / ${data.total} Sites`;

			if (data.has_more) {
				startFleetSync(data.next_offset);
			} else {
				logBox.innerHTML += '<div style="color:#6ee7b7; margin-top:8px; font-weight:bold;">✔ Fleet Sync Complete! Reloading dashboard...</div>';
				btnSync.disabled = false;
				btnReload.style.display = 'inline-block';
			}
		} else {
			logBox.innerHTML += '<div style="color:#ef4444; margin-top:8px;">⚠️ Retrying batch...</div>';
			setTimeout(function() { startFleetSync(offset + 5); }, 1000);
		}
	}).fail(function() {
		logBox.innerHTML += '<div style="color:#f59e0b; margin-top:8px;">⚠️ Re-establishing connection...</div>';
		setTimeout(function() { startFleetSync(offset + 5); }, 2000);
	});
}
</script>
