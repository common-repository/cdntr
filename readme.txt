=== CDNTR ===
Contributors: cdninternet
Tags: cdn, content delivery network, content distribution network
Tested up to: 6.6.1
Stable tag: 1.2.2
Requires at least: 5.7
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin that optimizes your website by delivering assets like CSS, JavaScript, and images via a content delivery network (CDN).

== Description ==
CDNTR is a user-friendly WordPress plugin designed to enhance your site's performance by delivering static assets such as CSS, JavaScript, and images through a content delivery network (CDN). By offloading the majority of your traffic to a CDN, this plugin helps to boost your site's speed, reliability, and scalability.

= Features =
* Fast and efficient rewrite engine
* Easy cache purging (when a [CDNTR](https://cdn.com.tr) account is connected)
* Include URLs in the rewrite by file extensions
* Exclude URLs in the rewrite by strings
* WordPress multisite network support
* WordPress REST API support

= How does the rewriting work? =
CDNTR captures page contents and rewrites URLs to be served by the designated CDN.


= Third Party Service Usage =
This plugin utilizes external services to provide its functionality. Specifically, it interacts with the CDN.com.tr service for content delivery network (CDN) related operations. This includes actions like purging cached content and checking account status.

*Services Used
*Service Name: CDN.com.tr
*Purpose: The service is used to purge all cached content and to check the account status.
*Endpoints:
*Purge All Cache: https://cdn.com.tr/api/purgeAll
*Check Account Status: https://cdn.com.tr/api/checkAccount
*Privacy and Terms
*Privacy Policy: https://cdn.com.tr/en/privacy
*Terms of Use: https://cdn.com.tr/en/privacy
*Please be aware that using this plugin means that your data will be sent to the aforementioned service. Ensure that you review the service's terms and policies to understand how your data is being used and to comply with any legal requirements.

= Maintainer =
* [CDNTR](https://cdn.com.tr)

== Screenshots ==

1. screenshot-1.png

== Changelog ==

= 1.2.2 =
* screenshots and assets added

= 1.2.1 =
* Minor security updates