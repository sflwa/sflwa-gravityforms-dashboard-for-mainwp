=== MainWP Gravity Forms Triage ===
Contributors: sflwa
Donate link: https://southfloridawebadvisors.com/
Tags: mainwp, gravity forms, triage, dashboard, churn
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.0.20
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-efficiency Gravity Forms status board for MainWP. Tracks form activity, spam counts, churn alerts, and 3-month rolling averages across child sites.

== Description ==

MainWP Gravity Forms Triage provides agency owners and web advisors with high-efficiency, actionable analytics across 300+ child sites managed through MainWP. 

Instead of forcing you to navigate site-by-site or review raw, unorganized entry logs, this plugin aggregates submission statistics, calculates 3-month rolling averages, and flags disengaged or dropping accounts using configurable volume thresholds.

=== Key Features ===

* **Unified Executive Status Board:** Monitor all child site submission statistics, 3-month rolling averages, and spam metrics in a single interactive table.
* **Smart Churn Diagnostics:** Automatically generates human-readable explanations ("Why" diagnostics) detailing exact form-level drops and entry halts.
* **Configurable Volume Tiers:** Define custom volume buckets (Low, Medium, High) and percentage drop thresholds to eliminate false positive alert noise on low-volume accounts.
* **Direct MainWP Admin Access:** One-click single sign-on links launch child site admin dashboards directly in a new tab via MainWP's native OpenSSL tunnel.
* **Non-Blocking Background Sync:** Built-in WP-Cron engine periodically syncs child site inventories in small, memory-friendly batches without browser timeouts.
* **Live Client & Search Filters:** Instantly search sites by name or filter table rows by MainWP Client assignment and risk level.
* **SFLWA Shared Support Integration:** Seamlessly integrates with the central SFLWA Support Handler dashboard.

== Installation ==

1. Upload the `sflwa-gravityforms-dashboard-for-mainwp` folder to the `/wp-content/plugins/` directory (or install via the WordPress admin panel).
2. Ensure the **MainWP Dashboard** plugin is installed and active on your primary hub site.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Navigate to **GF Status Board** in the WordPress admin menu to run your initial fleet synchronization.

== Frequently Asked Questions ==

= Does this plugin need to be installed on child sites? =
No. MainWP Gravity Forms Triage runs exclusively on your MainWP Dashboard hub site and executes secure code snippets remotely to retrieve entry statistics.

= How does the 3-Month Rolling Average baseline work? =
The analytics engine averages active submissions across the previous three full calendar months (excluding current month-to-date partial numbers). This ensures seasonal spikes and one-time imports naturally wash out over time without triggering perpetual false alarms.

= How do I adjust alert sensitivity? =
Click the **⚙ Threshold Settings** button on the dashboard to adjust volume caps and drop percentage triggers for Low, Medium, and High traffic tiers.

== Changelog ==

= 1.0.20 =
* Feature: Integrated conditional `sflwa_register_support_page()` registration for the SFLWA Shared Support Handler.
* Feature: Implemented timezone-aware WP-Cron schedule calculation displaying next auto-sync target date/time.
* Enhancement: Standardized all submission averages to whole integers.

= 1.0.19 =
* Feature: Added direct MainWP child site admin launch links (`SiteOpen`) to site names in table rows and drawer headers.
* Fix: Added `admin_init` self-healing check (`maybe_schedule_cron`) to ensure WP-Cron monthly schedules re-register automatically if dropped.

= 1.0.18 =
* Feature: Introduced Fleet Milestones header bar highlighting top submitting site, top form, zero-entry count, and next cron schedule.
* Optimization: Refined remote snippet payload for incremental monthly delta syncs.
* Formatting: Enforced whole integer rounding across 3-month rolling averages and diagnostic text.

= 1.0.17 =
* Feature: Added client-side column sorting across all numeric and string table headers.
* Feature: Added real-time text search filter input above executive table.
* Fix: Added `data-sort` numeric attributes to table cells for accurate DataTables sorting.

= 1.0.16 =
* Architecture: Removed top card grid to eliminate visual clutter; transitioned alerts exclusively into color-coded table rows.
* Feature: Added automated "Why" human-readable diagnostic text generator for form-level drop analysis.
* Feature: Added configurable volume tiers (Low, Medium, High) and modal settings handler (`mwp_gf_triage_settings`).

= 1.0.15 =
* Analytics: Shifted baseline logic from rigid Month-over-Month comparison to a 3-Month Rolling Average baseline.
* Reliability: Reduced AJAX batch size to 5 sites with 100ms micro-pauses and automatic error retries to prevent network timeouts across 300+ sites.
* UI: Unified client filter dropdown across focus cards, summary table, and accordion explorer.

= 1.0.14 =
* Fix: Corrected MainWP client query to target `wp_mainwp_wp_clients` (`name` column) using `client_id`.
* Enhancement: Added form-level previous vs. prior month entry counts and MoM badges inside accordion drawers.
* Fix: Removed artificial `LIMIT 10` execution constraints from AJAX batch handler.

= 1.0.13 =
* Fix: Shifted MoM baseline calculations to exclude current month-to-date data, comparing last completed month vs. prior completed month.
* UI: Dynamic human-readable month labels added to table headers.

= 1.0.12 =
* Feature: Added top-level Sites of Concern focus board and dual-tab view switcher (Executive Summary Table vs. Detailed Fleet Explorer).
* Fix: Multi-layer MainWP client resolver added to prevent "Unassigned Client" fallbacks.

= 1.0.11 =
* Fix: Added self-installing table creation check (`maybe_install_tables`) on class constructor execution.
* Fix: Added database table existence safeguards across all `wpdb` query methods.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.20 =
Includes SFLWA Shared Support Handler integration, timezone-aware WP-Cron auto-sync schedule indicators, and direct MainWP Child Site launch links.