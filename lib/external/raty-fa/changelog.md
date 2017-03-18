# jQuery Raty FA - A Star Rating Plugin with Font Awesome - https://github.com/Jacob87/raty-fa

## 0.1.1

### News

+ Fork of [jQuery Raty](https://github.com/wbotelhos/raty)
+ Pull request add from user [vaff](https://github.com/vaff/) with [jQuery Raty FA](https://github.com/vaff/raty-fa/)

### Changes

+ Adjusted the [Font Awesome](http://fontawesome.io/) icons to use .fa-fw class to give all icons a fixed with. 
+ Updated test cases to reflect above changes.
+ Updated test cases to use proper [Font Awesome](http://fontawesome.io/) clases.

## known Bugs

+ On versions before 1.6 if a attribute not exists, then empty string is returned instead undefined;
+ On 1.5.2 the opt.click callback is never called, because this version always returns undefined to .call() and .apply();
+ On 1.5.2 the attribute readonly is setted with empty value as following readonly="".
