=== Meeple Like Us Boardgamegeek Plugin ===

Contributors: drakkos
Donate link: https://www.patreon.com/meeplelikeus
Tags: boardgame, gaming, accessibility, boardgamegeek
Requires at least: 3.0.1
Tested up to: 5.7
Requires PHP: 5.2.4
License: CC-BY 4.0
License URI: https://creativecommons.org/licenses/by/4.0/
 
Boardgamegeek and Meeple Like Us integration for Wordpress.  Suitable for board game blogs.

== Description ==

Note: This plugin makes use of an external API that is to be found at http://imaginary-realities.com/bggapi/.  This is a service hosted via JustHost in the USA.  

If you're interested in using the [Meeple Like Us](http://meeplelikeus.co.uk) plugin for your WordPress site, you're in the right place!   Most of you I suspect will want it for the Boardgamegeek plate you can place on your site and have automatically updated, but some of you might be keen on including some accessibility ratings in your site.  The plugin lets you do both - all you need to know is the ID of the game, which you'll find by searching Boardgamegeek :

See http://meeplelikeus.co.uk/meeple-like-us-plugin/ for usage details

= Usage =

See [The documentation on Meeple Like Us](http://meeplelikeus.co.uk/meeple-like-us-plugin/)

== Frequently Asked Questions ==

=Am I going to end up spamming BGG with this?=

No!  All the actionable information is processed on my server and cached - the first site to ask for details of a game will result in a query being made against the BGG web service.   Every other request will get the cached version for about twelve hours.   After twelve hours, the information times out - that means the results aren't real time but they're going to be a lot more up to date than tracking and changing it all yourself.

=I have a lot of traffic.  Can your server cope?=

Probably!  Once you make a request against the server, it'll cache it locally for about six hours so that it is more responsive for you and less burdensome for me.

=Is this going to cost me money at any point?=

No.   I wrote this mainly for my benefit, and the BGG terms of use explicitly prohibit the use of their data for commercial purposes.   The benefit I get from the plugin comes from users linking to the accessibility work I'm doing when they make available a BGG plate on their page.

=Can I get rid of the link to your site in the header of the BGG info plate?=

No - the benefit I get from the plugin being out in the wild is that I generate more awareness for the accessibility work we do on Meeple Like Us.   There is no premium version of the plugin that will remove that, but if there's enough interest I could investigate making one available.

=Do I need an account or something to use it?=

No, although at some point in the future I might implement some kind of API key system to properly manage it at the server end.

=I have a feature request!=

Drop me a message, but I can't guarantee anything - I already have more projects than time.  Features primarily get added to the plugin on the basis of what benefit they bring to my site.  I'll prioritise feature requests for those supporting me on Patreon.  

=I have a bug report=

Drop me a message!

=Can you help me set this up?=

Probably not, sorry - I'll make exceptions for Patrons.

=What if it messes up all my data?=

It shouldn't, but you know - buyer beware.   I will give you a full refund for what you paid if that happens.

=What if I link to a game that Meeple Like Us doesn't cover?=

It should fail gracefully - the BGG plate should just not mention it, and any accessibility charts and tables will just be invisible instead.

== Changelog ==

=1.6.5=

* Fix for an infinite loop that I coded in like an idiot.  Fails more gracefully now.

= 1.6.2 =

* Added support for [mlu_bgg_hob_coc].  This permits a site to link, with appropriate language, to the Hobbyist Media Code of Conduct if they wish to adopt it.
* Added support for [mlu_label] which can be used to create arbitrary tabled divs in text that can be styled with already set CSS.  
* Various fixes.

= 1.5.0 =

* Added support for [mlu_rating].  For this, font-awesome needs to be available on the site.   It permits a five star rating scale, with halves, with a TLDR summary that can be configured in the options and at the level of an individual game.  For example, [mlu_rating rating=2] or [mlu_rating rating=3 desc="This is a three star game with a different TLDR"]

= 1.4.0 =

* Can now use the notes attribute in the bgg tag, such as [bgg id=1234 notes="This is a game that I like"]
* Backend API work 

= 1.2.0 =

* Added search functionality to collections
* Added a new shortcode - [mlu_scotlight].  It shows a searchable table from our Scotlight program.
* Backend API work 


= 1.1.0 =

* Added support for extracting BGG collections with [bgg_collection username=yournamehere].  BGG has a delay on this though so the first time you try to do this you'll only get a 'please wait' message until the request is fully processed.  From that point on, caches should make sure that doesn't happen again. 

= 1.0.0 =

* Made the link to the plugin description under the table more discrete.
* Stable enough to release as 1.0.0

= 0.9.6 =

* use-class attribute added to bgg_complexity.
* Fixed a few small bugs with rounding.
* Added the mlu_table shortcode - this will give you category stats for any of the following:  ColourBlindness, VisualAccessibility, Memory, FluidIntelligence, PhysicalAccessibility, EmotionalAccessibility, SocioeconomicAccessibility or Communication.  For example, [mlu_table category="ColourBlindness"]

= 0.9.4 =

* New customisation options for localisation
* Fixed a small bug with the mlu_radar chart
* Added support for geo-localised shop front links (none yet)
* Added the use-class attribute to bgg_rating.  This will apply a div and a set of classes suitable for CSS styling

= 0.9 =
* Various fixes
* Many more options available in the settings menu - configurable text
* Icon added to side menu
* Several new short-codes for independent pieces of information - [bgg_rating], [bgg_rank], [bgg_complexity].  Can be used in-text.  
* External links now target _blank by default.

= 0.8.4 =
* Various fixes
* new shortcode [mlu_master]!
* Letter grades for mlu_table will now be coloured

= 0.8.1 =
* First release!


