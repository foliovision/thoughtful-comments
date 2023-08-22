=== FV Thoughtful Comments ===

Contributors: FolioVision
Tags: comments,moderation,frontend,unapproved
Requires at least: 4.2
Tested up to: 6.3
Stable tag: trunk

FV Thoughtful Comments adds front end comment moderation including sophisticated banning mechanisms. Say Goodbye to Disqus!

== Description ==

We’ve always found the comment moderation/management a bit weak (no wonder so many people are using the Disqus crutch). Our plugin Thoughtful Comments supercharges comment moderation by moving it into the front end (i.e. in context). It also allows banning by IP, email address or domain.

Unlike many comment plugins, Thoughtful Comments works hand in hand with Akismet, feeding all the information into Akismet as well as the existing WordPress whitelist and blacklist features.

What’s cool about Thoughtful Comments is that you can add it to a WordPress site with no changes to existing comment moderation tables and you can remove it from a WordPress site with no loss of core functionality. I.e. I think Thoughtful Comments could be integrated into core with a minimum amount of pain. Thoughtful Comments works with all current Subscribe to Comment plugins as well. As we use all core functions and tables, Thoughtful Comments works with all current Subscribe to Comment plugins as well.

Thoughtful Comments is the most powerful and useful code we’ve ever written (we have four very popular plugins). It’s integration into core would save many, many site owners the pain of Disqus.

Thoughtful Comments is entirely stable and active on some of the most heavily commented political and lifestyle sites in the world.

While Automattic has a horse in the ring (Intense Debate), we'd really like to see Thoughtful Comments included in core.

###Features:

* Front-end comment moderation - for logged in users with required permission
* Unapproved comments shown in front-end - for logged in users with required permission
* Per-user moderation settings
* Comment caching - lightening PHP load and speeding up busy sites significantly - works with any WP cache plugin!

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
4. Admin screen settings.

== Changelog ==

= 0.3.5 =
* Fix "Reply link" function to disable HTML anchors for each comment reply for WordPress 5.1 and above

= 0.3.4 =
* Fix for PHP warnings in WordPress 5.5
* Fix for broken pending comment highlight in front-end in TwentyTwenty theme

= 0.3.3 =
* Fix for PHP warnings in WordPress 5.3
* Hiding cache file paths

= 0.3.2 =
* Link shortening improvements - shorten to "link to domains.com", 50 or 100 characters.

= 0.3.1 =
* performance improvements - fv_tc::comment_has_child() not used for front end, the thread buttons visibility is instead handled by JavaScript

= 0.3 =
* comment caching added - enable in plugin settings and gain considerably faster post load times for posts with high number of comments

= 0.2.9.1 =
* fix for comments going into moderation on websites with moderation disabled - caused by the permission check during the comment validation

= 0.2.9 =
* fixed frontend moderation for editor and author roles

= 0.2.8 =
* fix for broken "Trash and Ban IP" in wp-admin

= 0.2.7 =
* added auto-approving functionality for users, which have N comments already approved
* added possibility to change user nicename for administrator
* changed layout of admin screen
* removing comment-page-1 from comment links as it's not needed http://www.blindfiveyearold.com/wordpress-duplicate-content

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