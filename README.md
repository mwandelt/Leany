# What is Leany?

Leany is a lean, no-frills PHP template compiler that is ideally suited for web
application development following the MVC paradigm. The compiler has less than
200 lines of code, runs very efficiently, has a buit-in cacheing mechanism and
is easily extendable.


## Usage

```php
require_once 'leany.class.php';
$leany = new Leany( __DIR__ );
$code = $leany->compile('example.html');
eval( '?>' . $code );
```

## Syntax

Leany's placeholders and tags are written in double curly braces:

`{{headline}}`

They may be immediately surrounded by comment markers; those markers will later be removed in the generated code:

`<!--{{else}}-->`

PHP code may be embedded directly using the normal PHP start/end tags:

`<?php echo "This is some special code."; ?>`


#### Conditional Statements

If part of the template code should only be visible under certain conditions, use an `if` and an optional `else` statement:

```html
<!--{{if: condition}}-->
   <p>This is only visible when the variable $condition is not empty and not FALSE.</p>
<!--{{else}}-->
   <p>This is visible when the variable $condition is empty or FALSE.</p>
<!--{{endif}}-->
```
 You can cascade conditions via `elseif` tags:
 
 ```html
 <!--{{if: condition1 }}-->
    <p>This is visible when the variable $condition1 is not empty and not FALSE.</p>
 <!--{{elseif: condition2 }}-->
    <p>Otherwise, this is visible when the variable $condition2 is not empty and not FALSE.</p>
 <!--{{elseif: condition3 }}-->
    <p>Otherwise, this is visible when the variable $condition3 is not empty and not FALSE.</p>
 <!--{{else}}-->
    <p>Otherwise, this is visible.</p>
<!--{{endif}}-->
 ```

The `if` and `elseif` statements need a parameter which corresponds to the name
of a PHP variable without the dollar sign. The condition is met when the PHP
`empty` function, applied to this variable, returns a boolean TRUE.


#### Repetitional Statements

If part of the template code should be repeated multiple times, use a `repeat` statement:

```html
<!--{{repeat: myList }}-->
   <p>This is repeated for every element of the $myList array.</p>
<!--{{endrepeat}}-->
```

The `repeat` statement needs a parameter which corresponds to the name of a PHP
variable without the dollar sign. This variable should point to an array. On
each repetition the variable `$item` will be set to the value of the current
array element:

```html
<ul>
   <!--{{repeat: weekdays }}-->
      <li>{{item}}</li>
   <!--{{endrepeat}}-->
</ul>
```

You may specify a more meaningful name for the current element by adding a pipe
symbol and the desired name. This is especially important when you want to nest
multiple `repeat` statements:

```html
<!--{{repeat: weeks | weekno }}-->
<h3>Week No. {{weekno}}
<ul>
   <!--{{repeat: weekdays | day }}-->
      <li>{{day}}</li>
   <!--{{endrepeat}}-->
</ul>
<!--{{endrepeat}}-->
```

If you traverse an array of associative arrays ("object list"), you may output an individual object property by adding a dot and the property name to the placeholder:

```html
<h3>Price List</h3>
<table>
   <tr>
      <td>Code</td>
      <td>Name</td>
      <td>Price</td>
   </tr>
   <tr>
      <!--{{repeat: products }}-->
         <td>{{item.code}}</td>
         <td>{{item.name}}</td>
         <td>{{item.price}}</td>
      <!--{{endrepeat}}-->
   </tr>
</table>
```

#### Database Query Results

For traversing the result sets of database queries Leany provides a special
`loop` statement which makes it unneccessary to build an array first. 

```html
<h3>Contacts</h3>
<table>
   <tr>
      <td>First name</td>
      <td>Last name</td>
      <td>Phone</td>
   </tr>
   <tr>
      <!--{{loop: contacts }}-->
         <td>{{item.firstname}}</td>
         <td>{{item.lastname}}</td>
         <td>{{item.phone}}</td>
      <!--{{endloop}}-->
   </tr>
</table>
```

The `loop` statement needs a parameter which corresponds to the name of a PHP
variable without the dollar sign. This variable must point to an object which
supports the `data_seek` and `fetch_assoc` methods, for example, a `mysqli_result`
object which is returned by the `mysqli_query` function.

As in the `repeat` statement you may specify a meaningful name for the variable
holding the current record:

```html
<h3>Contacts</h3>
<table>
   <tr>
      <td>First name</td>
      <td>Last name</td>
      <td>Phone</td>
   </tr>
   <tr>
      <!--{{loop: contacts | contact }}-->
         <td>{{contact.firstname}}</td>
         <td>{{contact.lastname}}</td>
         <td>{{contact.phone}}</td>
      <!--{{endloop}}-->
   </tr>
</table>
```



#### Includes

Code which is shared among multiple templates may be stored in an external file
and included via `include` statement:

```html
<body>
   {{include: header.html }}
   ...
   {{include: footer.html }}
</body>
```

If you use relative file paths the include files must be located in the base
directory specified via `baseDir` property, or in one of its subdirectories.


#### Links

Leany provides a special `link` placeholder for dealing with relative
inter-application links:

`<a href="{{link:admin/settings}}">Settings</a>`

This placeholder will be replaced with the relative URL, which you provide as
parameter, added to the base url set in the `baseUrlLinks` property.


#### Variables

Placeholders which are not recognized as pre-defined statements (see above) are
treated as simple variables. For example, the placeholder `{{username}}` will be
replaced with the value of the variable called `$username`. Elements of
associative arrays may be addressed with their key name added to the variable
name, separated by a dot. So, the placeholder `{{user.name}}` would be replaced
with the value of `$user['name']`.

Variable values may be pre-processed by a custom filter function. For Details
see the section "Output Filtering" below.


## Cacheing

Leany works very efficiently, but in high-traffic scenarios it is a good
idea to cache the generated PHP code. To enable the cacheing mechanism just
hand over the path of a writable directory as second argument to the constructor
function, or copy it manually in the `cacheDir` property of the Leany object.

A cached code file is automatically renewed when the corresponding template file
changes. You can tell Leany to ignore any cache file by setting the second
argument of the `compile` method to `TRUE`. If you want to clear the cache
completely, just call the `clear_cache` method of the compiler object.

Cacheing does not only reduce the CPU usage of your application, but may also be
helpful when  debugging it. Especially when it comes to PHP parse errors a look
at the generated source code may point you directly to the cause of the problem.


## URL Rewriting

Leany is able to rewrite relative URLs contained in template files, so it is
easy to work with external resources like stylesheets, images and JavaScript
files. In order to use this feature be sure to put all resources in
subdirectories of your template directory and set the `baseUrlResources`
property to its base URL. This base URL will be applied to all relative URLs
that contain at least one slash and end with a file extension (e.g. `.jpg`).


## Output Filtering

Leany allows you to pre-process all placeholders that are simple variables (see
above). Just set the `outputFunction` property of the Leany object to the name
of a globally available function. This function has to accept two arguments: the
value to be formatted, and an optional filter string which may be provided in
the template by adding a pipe symbol (`|`) and a string to the placeholder,
for example `{{created | date:d.m.Y}}`.

Following is an example output function which does a `var_export` when the value
is an array (this is helpful during development and testing), applies some date
formatting when the filter string starts with `date:`, or otherwise prepares the
value for embedding in HTML by converting special characters to HTML entities
and line breaks to `<br>` elements. This conversion may be skipped by adding
`|raw` to any placeholder.

```php
function my_output_function( $str, $type = '' )
{
	if ( is_array( $str ) )
	{
		return var_export( $str, TRUE );
	}

	if ( $type == 'raw' )
	{
		return $str;
	}

	list ( $type, $format ) = explode( ':', $type, 2 ) + array( '', '' );
	$function = 'format_output_' . $type;

	if ( function_exists( $function ) )
	{
		return $function( $str, $format );
	}
	
	return nl2br( htmlspecialchars( $str ) );
}


function format_output_date( $dateString, $format )
{
	if ( empty( $dateString) || $dateString[0] == '0' )
	{
		return '';
	}

	$time = is_numeric( $dateString ) ? (int) $dateString : strtotime( $dateString );
	
	if ( ! $time )
	{
		return $dateString;
	}
	
	if ( $format[0] == '%' )
	{
		return utf8_encode( strftime( $format, $time ) );
	}

	return utf8_encode( date( empty( $format ) ? 'YYYY-mm-dd' : $format, $time ) );
}
```

This simple filter mechanism may be easily extended by declaring additional
`format_output_xxx` functions, e.g. `format_output_price` for handling
placeholders with a `|price` filter.


## Extending the syntax

If you want the compiler to support more tags, just define a new class extending
the  original Compiler class and define a method for each tag. The  method
must be named `handle_tag_xxx` where `xxx` is the name of the tag.  The method may
take one parameter, which gets filled with the tag parameter (the string after
the colon), and returns a block of PHP code,  which will be evaluated after the
compilation. If you want to support the simplified parameter syntax you have to
parse the argument with the `_arg2var` method.
