# ViewFish PHP Templating Engine

ViewFish is a simple PHP templating library that is designed to be extremely simple to use. There are three steps to using a ViewFish template. 

First, you instantiate the object and, optionally, pass it the path to templates. 

*$t = new ViewFish\viewfish();*

Next, you load your template of choice: 

*$template	 = $t->load_template('/path/to/template-name.tmpl');*

Finally, you pass an associative array to the rendering function: 

*$html = $t->render($template,$data);*

That's it. `$html` will now contain your ready-to-go output. 

You could also the same thing like this: 
```php
$t = new ViewFish\viewfish('/path/to/templates/');
$template	 = $t->load_template('template-name');
$html = $t->render($template,$data);
```

For examples, visit the [ViewFish demo gallery](https://viewfish.sethadam1.com/examples).

## Working with ViewFish

### Variables 

ViewFish uses double curly braces for template variable, e.g. `{{variable}}`. If a curly-brace-wrapped variable matches an index of your $data array, it will be replaced by the value of that array element your rendered template. If it doesn't match an argument, it will be left alone.  

ViewFish uses double brackets for dynamic replacement. `[[year]]`, for example, will show the current year.  

### Functions 
You can also manipulate the variable using piped functions, e.g. `{{variable|function}}`. Note that functions can be chained, meaning `{{variable|function1|function2|function3}}` is valid syntax.  

The functions that are current supported by default are: 
* trim 
* ucfirst 
* ucwords   
* nl2br 
* strtoupper
* strtolower
* htmlspecialchars
* number_format
* stripslashes
* strip_tags
* md5
* intval

The following short-hand functions are supported: 
* upper &mdash; wrap output in strtoupper
* lower &mdash; wrap output in strtolower
* escape &mdash; escape output
* e &mdash; an alias of <i>escape</i>
* sup &mdash; wrap output in sup tags
* sub &mdash; wrap output in sub tags

### Multi-argument functions
ViewFish templates support multi-argument functions in the format `{{var|function:arg1:arg2:arg3}}`. Currently, you can use the following functions: 

* substr:offset:length, e.g. `{{str|substr:3:10}}`

You can also use the shortcut function `ellipsis` to trim a string if it exceeds the argument. For example, `{{var|ellipsis:18}}` will trim and append "..." to a $var only if it exceeds 18 characters in length, including spaces. 

### Extention
ViewFish can handle other functions in templates using the extend() method. For example:  
*$t->extend(['function1','function2','function3']);* 
will add additional functionality to the templating process. 

A few notes: functions will only work if they 1) accept a string with no further arguments and 2) return a string. The functions that ViewFish Templating is known to support are: `addcslashes, addslashes, bin2hex, chop, chr, chunk_split, convert_cyr_string, convert_uudecode, convert_uuencode, count_chars, crc32, crypt, get_html_translation_table, hex2bin, html_entity_decode, htmlentities, htmlspecialchars_decode, lcfirst, ltrim, metaphone, money_format, ord, quotemeta, rtrim, sha1, soundex, str_rot13, str_word_count, stripcslashes, strlen, strrev, strtok, floatval, ceil, floor`

ViewFish will **not** accept the following functions as they can create dangerous execution conditions:
`exec, system, passthru, shell_exec, popen, proc_open, pcntl_exec, eval, assert, create_function, call_user_func, call_user_func_array, file_get_contents, file_put_contents, fopen, fwrite, unlink, rmdir, mkdir, rename, copy, move_uploaded_file, curl_exec, curl_multi_exec, parse_str, extract, putenv, ini_set, dl, mail, header, preg_replace`

### Other syntax
`{{date|format}}` is supported, where format is an unquoted string using the arguments at php.net/date. 

### Comments 
ViewFish templates support multiline comments wrapped in either `{* curly brace star tags *}` Smarty style tags or `/* C style comments */`. They will be stripped from the rendered template.

ViewFish templates also support single line comments using the `// double slash` syntax.  

### Dynamic Placeholders
Dynamic placeholders will be replaced in the rendered template, but accept no arguments. 

* `[[year]]` will display the current year.  
* `[[uniqid]]` will generate a unique - [but not unguessable](https://www.php.net/uniqid) - identifier. 
* `[[timestamp]]` will generate a current UNIX timestamp 
* `[[datetime]]` will generate a MySQL compatible timestamp in the current server timezone, e.g. YYYY-mm-dd 24:00:00
* `[[utcdatetime]]` will generate a MySQL compatible timestamp in UTC, e.g. YYYY-mm-dd 24:00:00

## Interacting with the ViewFish object
*$t->extend(['function1','function2','function3']);* will allow you to add additional supported functions 

*$t->unextend()* will reset the allowed functions list to the small list of permitted default functions 

*$t->load_supported_functions()* will load all known supported string functions

## Caching
ViewFish supports a number of in-meory caching operations. In order to use caching, you'll need to have either [Memcache](https://www.php.net/memcache) or [Memcached](https://www.php.net/memcached) enabled. Once you have a Memcached object, you will pass it to your ViewFish object using the _enable_cache()_ method. 
  
*$t->enable_cache(resource $memcache, int $ttl)* will enable memory caching of uncompiled templates. You can pass a Memcache or Memcached resource to enable to cache. An optional $ttl will specify the "time to live" of your memcached object, which defaults to 300 seconds. You may want to set $ttl to a large number to reduce file system reads. 1 day - 86400 seconds - or 30 days - which is a value of 2592000 - are reasonable numbers for templates that don't change often.   
 
*$t->uncache()* will delete the memcached entry for a template. If you've made changes to a template with a long $ttl, you can uncache it.   

ViewFish allows you to store compiled templates as well. **You should not use this for user specific data!** You can do this manually or automatically. 

*$t->cache_compiled(true)* will enable the caching of compiled templates. By default, this option is set to false. 

Similarly, *$t->cache_compiled(false)* will disable caching of compiled templates. 

*$t->cache_create($tmpl,$text,$ttl)* will manually cache a template. $tmpl is the name of the template, $text is the compiled template text, and $ttl is the cache length, which defaults to 86400, or 1 day. If you attempt to store a template that already exists, **it will fail**. 

*$t->cache_create($tmpl,$text,$ttl)* will manually cache a template. $tmpl is the name of the template, $text is the compiled template text, and $ttl is the cache length, which defaults to 86400, or 1 day. If you attempt to store a template that already exists, **the old value will be overwritten by the new one**. 

*$t->cache_read($tmpl)* will retrieve a compiled template from cache. 

*$t->cache_destroy($tmpl)* will delete a compiled template from cache. 

### Nested templates
You can include templates within templates by using the syntax `{{@template file=template_name}}` where ```template_name``` is the file name of the template, e.g. _test.tmpl_. Templates can be nested within nested templates recursively, as well. 

Data within nested templates will be in the outer template's scope. In other words, you will load your $data for all templates, including nested templates, in the initial $data array.  To specify different values for nested templates, you can created an array within your data array with an index of the template name and rewrite the elements. For example: 

<pre>
template.tpl
	- template2.tpl
		- template3.tpl 
	- template4.tpl 
</pre>

<pre>
$data => Array[
	x => string_a,
	y => string_b,
	z => string_c,
	template2 = Array[
		z => string_d
	]
]
</pre>

In template 1 and 4, x will be "string_a", y will be "string_b", and z will be "string_d".

In template 2 and 3, however, x will be "string_a", y will be "string_b", and **z will be "string_d".** Because $data['template2']['x'] exists, it overwrites $data['x'] for template 2. And template 3 will be fed by template 2. If you want data for template 3, you'd need to create an array like this: 
 
<pre>
$data => Array[
	x => string_a,
	y => string_b,
	z => string_c,
	template2 = Array[
		z => string_d
		template3 => Array[
			y => string_e
			z => string_f
		]
	]
]
</pre>

### Conditionals: isset

ViewFish supports conditional blocks using `{{isset $var}}`. Content inside the block is only rendered if the variable is set and non-empty in your `$data` array.

```Smarty
{{isset $username}}
	<p>Welcome back, {{username}}!</p>
{{/isset}}
```

You can also use `{{else}}` to provide fallback content:

```Smarty
{{isset $username}}
	<p>Welcome back, {{username}}!</p>
{{else}}
	<p>Welcome, guest!</p>
{{/isset}}
```

### Conditionals: unless

`{{unless}}` is the inverse of `{{isset}}` — it renders content only when the variable is **not** set or is empty.

```Smarty
{{unless $logged_in}}
	<p>Please log in to continue.</p>
{{/unless}}
```

`{{unless}}` also supports `{{else}}`:

```Smarty
{{unless $error}}
	<p>Everything looks good!</p>
{{else}}
	<p>Something went wrong: {{error}}</p>
{{/unless}}
```

### Default Values

You can provide a default value for any variable using the `default` function. If the variable is not present in your `$data` array, the default value will be used instead.

```Smarty
{{username|default:"Guest"}}
{{color|default:'blue'}}
```

Default values can be combined with other piped functions:

```Smarty
{{username|default:"guest"|ucwords}}
```

In this example, if `username` is not set, the output will be "Guest" (the default value passed through `ucwords`).

### Loops
You can create loops in templates using the command ```@loop```. You must provide an argument called ```data```, where data is the name of the element in your $data array that itself contains an array of data. For example: 

```Smarty
<ul>
{{@loop data=people}}
	<li>{{firstname|ucwords}} {{lastname|ucwords}}</li>
{{/loop}}
</ul>
```

Would be called like so: 

<pre><div class='colorMe'>// prep the data array
$args['people'] = [
	['firstname'=>'Charlie', 'lastname'=>'Bucket'],
	['firstname'=>'Violet', 'lastname'=>'Beauregard'],
	['firstname'=>'Veruca', 'lastname'=>'Salt'],
];

// create the ViewFish object
$t = new ViewFish\viewfish('/path/to/templates/');

// load the template 
$template = $t->load_template('template-name.tmpl'); 

// render the template
echo $t->render($template,$args); </div></pre>

### Loop Index Variables

Inside a loop, ViewFish automatically provides several meta-variables you can use:

| Variable | Description |
|----------|-------------|
| `{{@index}}` | Zero-based index of the current iteration (0, 1, 2, ...) |
| `{{@count}}` | One-based count of the current iteration (1, 2, 3, ...) |
| `{{@first}}` | Truthy (`"1"`) on the first iteration, empty string otherwise |
| `{{@last}}` | Truthy (`"1"`) on the last iteration, empty string otherwise |
| `{{@total}}` | Total number of items in the loop |

Example using loop indexes:

```Smarty
<ol>
{{@loop data=items}}
	<li class="{{isset $@first}}first-item{{/isset}}">
		#{{@count}} of {{@total}}: {{name}}
	</li>
{{/loop}}
</ol>
```

For documentation, visit [viewfish.sethadam1.com](https://viewfish.sethadam1.com).

For examples on ViewFish use, visit the [ViewFish demo gallery](https://viewfish.sethadam1.com/examples).