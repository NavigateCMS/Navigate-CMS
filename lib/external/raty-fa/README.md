# jQuery Raty FA - A Star Rating Plugin with Font Awesome

## Description
jQuery Raty FA is a plugin that generates a customizable star rating with Font Awesome. In reality what the plugin does is to work with just about any classes on its' stars, which could be easily customized to one's own CSS classes.
The plugin is a fork of the original [jQuery Raty plugin](https://github.com/wbotelhos/raty) by Washington Botelho.

### Demo
[http://jacob87.github.io/raty-fa/](http://jacob87.github.io/raty-fa/)

## Attribution

What | Who
---------- | -------------
version | 0.1.1
since | 2014-04-10
author | [Jacob Overgaard](http://jovergaard.me)
contributors | [Dan Jessen](http://danjessen.dk)

## Required Files

+ jquery.js
+ jquery.raty.js
+ Library of Font Awesome


## Installation

### GitHub
Download the lib/jquery.raty-fa.js file

### Bower

```bash
bower install ratyfa
```

## Usage
### Options

```js
cancel      : false                                          // Creates a cancel button to cancel the rating.
cancelHint  : 'Cancel this rating!'                          // The cancel's button hint.
cancelOff   : 'fa fa-fw fa-minus-square'                           // Icon used on active cancel.
cancelOn    : 'fa fa-fw fa-plus-square'                            // Icon used inactive cancel.
cancelPlace : 'left'                                         // Cancel's button position.
click       : undefined                                      // Callback executed on rating click.
half        : false                                          // Enables half star selection.
halfShow    : true                                           // Enables half star display.
hints       : ['bad', 'poor', 'regular', 'good', 'gorgeous'] // Hints used on each star.
iconRange   : undefined                                      // Object list with position and icon on and off to do a mixed icons.
mouseout    : undefined                                      // Callback executed on mouseout.
mouseover   : undefined                                      // Callback executed on mouseover.
noRatedMsg  : 'Not rated yet!'                               // Hint for no rated elements when it's readOnly.
number      : 5                                              // Number of stars that will be presented.
numberMax   : 20                                             // Max of star the option number can creates.
precision   : false                                          // Enables the selection of a precision score.
readOnly    : false                                          // Turns the rating read-only.
round       : { down: .25, full: .6, up: .76 }               // Included values attributes to do the score round math.
score       : undefined                                      // Initial rating.
scoreName   : 'score'                                        // Name of the hidden field that holds the score value.
single      : false                                          // Enables just a single star selection.
size        : null                                           // The size (in pixels) of the icons that will be used.
space       : true                                           // Puts space between the icons.
starHalf    : 'fa fa-fw fa-star-half-o'                            // The name of the half star image.
starOff     : 'fa fa-fw fa-star-o'                                 // Name of the star image off.
starOn      : 'fa fa-fw fa-star'                                   // Name of the star image on.
target      : undefined                                      // Element selector where the score will be displayed.
targetFormat: '{score}'                                      // Template to interpolate the score in.
targetKeep  : false                                          // If the last rating value will be keeped after mouseout.
targetText  : ''                                             // Default text setted on target.
targetType  : 'hint'                                         // Option to choose if target will receive hint o 'score' type.
width       : false                                          // Manually adjust the width for the container.
```

### CSS
```css
<link href="//netdna.bootstrapcdn.com/font-awesome/latest/css/font-awesome.css" rel="stylesheet">
```

### HTML
```html
<div id="star"></div>
```

### JavaScript
```js
$('#star').raty();
```

## Functions

```js
$('#star').raty('score');                  // Get the current score.

$('#star').raty('score', number);          // Set the score.

$('#star').raty('click', number);          // Click on some star.

$('#star').raty('readOnly', boolean);      // Change the read-only state.

$('#star').raty('cancel', boolean);        // Cancel the rating. The last param force the click callback.

$('#star').raty('reload');                 // Reload the rating with the current configuration.

$('#star').raty('set', { option: value }); // Reset the rating with new configurations.

$('#star').raty('destroy');                // Destroy the bind and give you the raw element.
```

## License

The MIT License

Original jQuery Raty plugin by Washington Botelho
