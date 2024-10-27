=== Amazon Reloaded for WordPress ===
Contributors: nickohrn, thomas.baer
Donate link: http://plugin-developer.com/quote/
Tags: ecommerce, amazon, admin, write, post, page, affiliate
Requires at least: 2.8.0
Tested up to: 3.1.1
Stable tag: 5.0.8

This plugin allows a post author to quickly and easily insert text and image links to Amazon product pages into posts.

== Description ==

_Please note: *PHP5* is required for this plugin due to the use of json_encode, the DOMDocument class, and the plugin code syntax._

This plugin makes it easy for post authors to quickly search Amazon's index from their WordPress post interface and grab 
text links or images to place in their posts.  The text links can optionally contain an indivdual's affiliate tag, if entered
on the settings screen.  

The blog administrator can choose which Amazon locale that they wish to use from a list of the following: 

* United States
* United Kingdom
* Canada
* France
* Germany
* Japan

The new version introduces support for Amazon's new request signing requirements.  In addition, a credential checking system
has been put in place and the remote connection API used should work across more blogs and hosting setups.


== Installation ==

The installation of this plugin is simple. However, due to new Amazon restrictions some setup is required.

1. Upload the `amazon-reloaded-for-wordpress` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to the Amazon Reloaded submenu in the 'Settings' menu
1. Enter your Amazon API and Secret keys
1. Enter your Amazon Associates tag, if you wish

To ensure that you've entered the correct credentials, you can click the Check Credentials button after you have entered and saved your
credentials.  If you need to get an Amazon API key, please visit the Amazon Web Services site at http://aws.amazon.com/ and create an
account. 

== How To ==
The Amazon Reloaded for WordPress plugin is amazingly simple to use

1. Start writing a post or page and when you need to insert something, scroll down to the Amazon Reloaded meta box
1. Enter your search term and press the Search Amazon button or hit enter
1. You can optionally select a different search index if you're having trouble finding what you're looking for
1. To insert a link, click the "Send Link to Editor" button. 
1. To insert an image, select the size you want to use and then click the adjacent "Send Image to Editor" button.

