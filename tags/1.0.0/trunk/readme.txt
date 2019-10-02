=== Plugin Name ===
Contributors: tickerator
Donate link: http://www.tickerator.org
Tags: issue tracking, bug tracking, project tracking, submit bugs, issues
Requires at least: 3.4.1
Tested up to: 3.4.1
Stable tag: 1.0.0
License: GPLv2 
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple bug-tracking software easily managed on your Wordpress. Requires PHP 5.3.

== Description ==

This is a simple but complete issue tracking Wordpress plugin.  With it you can create projects and track the 
issues related to those projects.  
Features include:

* Ready to administer multiple projects right away.
* Easy install/uninstall. Uninstall is complete, nothing is left behind.
* Most output is template based so it is possible to customize the pages.
* Adds a menu option under the "Setting" menu of the dashboard.
* Display all issues, issues grouped by version and issue details.
* Can comment on issues.
* Administrator ability to edit comments & issues posted by others.
* Ability to add administrators/developers to a project without elevating their Wordpress priviledges.
* Add admins and developers by a simple drop down menu.
* Ability to administer all of the projects both from the internal Wordpress menu and the bugerator page.
* Ability to include file attachments or disallow file uploads.
* Plays well with other themes and plugins. Does not add custom Wordpress posts, uses its own tables.
* Choose to allow anonymous editing or require Wordpress usernames.
* Will automatically get rid of the WP comments section if desired.
* Automatic email sending to subscribed users.
* Most CSS options configurable in app.
* And more

== Installation ==


1. Upload `bugerator.zip` to the `/wp-content/plugins/` directory and unzip - or use the internal plugin install
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Enter the short code `[bugerator]` on a blank page.
1. To limit a page to a specific project get the project ID and have the short code be `[bugerator project='1']` if your project ID is 1.


== Frequently Asked Questions ==

= Who is this for? =

This is for anybody administrating a project.

= What makes this different/better than the other open source project management systems? =

Basically it was my desire to make it so that people could have a single login for everything.  So people could just have 
a Wordpress login instead of having a separate login for the issue tracking system.

== Screenshots ==

1. The initial project screen showing your choice of 3 projects (in this example)
2. The list of current issues with show hidden enabled. 
3. The version map in action.
4. The detail page of an issue.
5. A user's profile page with their options to receive email subscriptions
6. The ability to add/edit administrators without increasing their access to your blog.

== Changelog ==

= 1.0.0 =
* Initial release
* Has all of the features I could think of and fixed all the bugs that I could find

== Upgrade Notice ==

= 1.0.0 =
Initial release.