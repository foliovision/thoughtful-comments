=== FV Thoughtful Comments ===

Contributors: FolioVision
Tags: comments,moderation,frontend,unapproved
Requires at least: 2.7
Tested up to: 3.9.1
Stable tag: trunk

FV Thoughtful Comments adds advanced front end comment moderation and cool thread and user banning mechanisms to your Wordpress blog.

== Description ==

FV Thoughtful Comments adds advanced front end comment moderation and cool thread and user banning mechanisms.

Features:
**Front-end comment moderation**
**Unapproved comments shown in front-end**
**Per-user moderation settings**

All of these features apply to logged in editors and administrators only!

**[Download now!](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/)**

[Support](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/) | [Change Log](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/changelog/) | [Installation](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/installation/) | [Usage](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/usage/)


== Installation ==

Just copy the plugin directory into your Wordpress plugins directory (usually wp-content/plugins) and activate it in Wordpress admin panel.

You can modify the CSS file in the plugin (/wp-content/plugins/thoughtful-comments/css/frontend.css) to change the styling of plugin action links and the highlights.

== Theme compatibility ==

If you want to get the most correct display when deleting a comment and preserving it's replies, you need to use a theme which is using "cascade" display of the comments instead of "nested" display. 

Thoughtful Comments assumes that each comment is contained in some HTML element with unique ID which is containing the comment ID, so it works with most of the themes.

Also, commenter name should not be in cite tag, so that the HTML highlight will appear properly and not as readable HTML (similar to code tag).


== Screenshots ==

1. The front end comment moderation which editor and admin users are able to see and use.
2. Number of unapproved comments shown for editor and admin users in the frontend. The number is highlighted as there are some unapproved comments and there's a tooltip.
3. Per-user moderation settings.

== Changelog ==

= 0.2.6 =
* fix "Delete comment and Ban IP" button also report comment as spam if Akismet plugin is activated

= 0.2.5 =
* fix for unapproved commenter name in comment_author() template tag
* fix for plugin admin JS failing on some sites (uses simple current_user_can moderate_comments check)
* added support for translations
* improved AJAX handling (uses admin-ajax)

= 0.2.4. =
* link directly to new comment in comment notifications
* added plugin options page with two options: Link shortening and Reply link

= 0.2.3.3 =
* bugfix - check if plugin supporting class exists

= 0.2.3.2 =
* fix for Thesis (our links were not appearing bellow comments)

= 0.2.3.1 =
* unapproved comment count hilight bugfix

= 0.2.3 =
* bug fix of admin js
* more features coming in 0.3!

= 0.2.2 =
* better operation with trashed comments and parent - child comment relations (will preserve this relation even when restoring a trashed comment)
* fixed bug in unapproved comment hilight

= 0.2.1 =
* Minor bug fix

= 0.2 =
* Performance fix

= 0.1.5 =
* Better support for author user level

= 0.1 =
* Initial release



== Frequently Asked Questions ==

[Support](http://foliovision.com/seo-tools/wordpress/plugins/thoughtful-comments/)
