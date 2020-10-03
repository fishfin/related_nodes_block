CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Known Issues
 * Troubleshooting
 * Developers and Maintainers
 * Credits
 * Postscript


INTRODUCTION
------------

The Related Nodes Block module provides nodes blocks that are related to the
node that they are displayed with, related by Content Type. This provides a
very easy way of creating the following list of nodes:
 * Previous
 * Next
 * Most Viewed Today
 * Least Viewed Today
 * Most Viewed All-time
 * Least Viewed All-time
 * First
 * Last
 * Random
of Nodes.

The following node filters are available:
 * A specific node
 * Nodes of the same content type as the current node page
 * Nodes not of the content type of the current node page
 * Nodes of selective content types

The output generated can be displayed in the following ways:

 * Linked text with tokens (literal text or node tokens or a combination)
 * Use content view modes


 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/pathauto

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/pathauto


REQUIREMENTS
------------

This module requires the following:

 * Drupal Core - 8.8+ or 9.0+
 * Block - Drupal Core Module
 * Node - Drupal Core Module
 * Statistics - Drupal Core Module
 * Token - Drupal Contrib Module, https://www.drupal.org/project/token


RECOMMENDED MODULES
-------------------

 * Context - https://www.drupal.org/project/context


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

Each block generated can be configured as follows:

 1. Go to Administration > Structure > Block layout.
 2. Select your region and click the "Place block" button.
 3. On the "Place block" form, search for "Related Nodes Block".
 4. Make your selections for Filter and Display options.
 5. Make your selections for Visibility options. If you have installed the
    Context module, you will be able to use some additional options.
 6. Click "Save block".


KNOWN ISSUES
------------

 1. 'Reverse natural order' should become invisible when at least one of the
    following conditions is true:
    a. When specific node is selected (makes AJAX callback) OR
    b. Limit is set to 1 OR
    c. Random display is selected
    This means that 'Specific' makes AJAX request, and state of 'Reverse natural
    order' depends on that field. Even when this condition is true, the field
    stays visible (as of Sep 2020). See Drupal bug:
    https://www.drupal.org/project/drupal/issues/1091852
 2. 'View Mode' dropdown is dynamically AJAX-updated based on number of
    selections. Its state of being required or visible also depends on these
    conditions. As of Sep 2020, due to the bug noted above, the 'required'
    state does not work as expected, and it removes the red '*' symbol. A
    specific validation has hence been added to blockValidation method to
    force a value (though red '*' will still be missing).


TROUBLESHOOTING
---------------

Q: Why does the View Mode render Linked Text?
A: If a particular view mode was selected at the time of configuration of a
   block, but subsequently the view mode was disabled for the content type of
   the filtered node, or view mode was altogether removed from the system,
   the block is rendered with a linked title only.
   Also, if multiple Content Types are selected for the filter, and if
   at least one node is encountered that does not implement the selected
   View Mode, all filtered nodes fallback on the Linked Text.


DEVELOPERS AND MAINTAINERS
--------------------------

Original Developer:

 * Aalap Shah (fishfin) - https://www.drupal.org/u/fishfin

Current maintainers:

 * Aalap Shah (fishfin) - https://www.drupal.org/u/fishfin


CREDITS
-------

Credits to the following contributed modules for getting the developer started
on writing a drupal block module:

 * Next Previous Post Block - https://www.drupal.org/project/nextpre
 * Quick Node Block - https://www.drupal.org/project/quick_node_block

And the Nalanda Mahavihara library of knowledge shared by fellow developers on
Drupal.org and stackoverflow.com! THANK YOU!


POSTSCRIPT
----------

Made in India with <3 by fishfin. Created Sep, 2020.

"I'd made it this far and refused to give up,
 Because all my life I had always finished the race."
 - Louis Zamperini
