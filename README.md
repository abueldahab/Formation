Formation
=========

**A powerful form creation composer package for Laravel 4 built on top of Laravel 3's Form class.**

Formation makes it really easy to populate form fields with default values and to build a form with powerful form field building methods that automatically add an "error" class to labels and form fields and provides the ability to validate specific arrays in the POST array.

	<input name="user[name]" value="" />
	<input name="user[email]" value="" />

	<input name="other_field" value="" />

With this form, we can validate just the fields in the user array with `Form::validated('user')`, the final field with `Form::validated('root')`, or all of the fields in the form with `Form::validated()`.

This and many other features make Formation a useful addition to any Laravel 4 project that makes any reasonable use of forms and a great aid in transitioning a web application from Laravel 3 to Laravel 4.

> You may notice much of this documentation is borrowed from Taylor Otwell's Laravel 3 documentation. This is because Formation was built with Laravel 3's Form class as a starting point. If you are familiar with the Form class for Laravel 3, you will adapt to Formation very easily.

- [Installation](#installation)


- [Opening a Form](#opening-form)
- [CSRF Protection](#csrf-protection)
- [Default Form Values](#default-values)
- [Validation Rules](#validation-rules)
- [Labels](#labels)
- [Full Array Setup for Labels, Validation Rules, and Default Values](#full-array-setup)
- [Text, Text Area, Password & Hidden Fields](#basic-fields)
- [File Input](#labels)
- [Checkboxes and Radio Buttons](#checkbox-radio)
- [Checkbox and Radio Button Sets](#checkbox-radio-sets)
- [Drop-Down Lists](#drop-down-lists)
- [File Input](#file-input)
- [Buttons](#buttons)
- [Field Macro](#field-macro)
- [Custom Macros](#custom-macros)

> **Note:** All input data displayed in form elements is filtered through the entities method.

<a name="installation"></a>
## Installation

To install Formation, make sure "aquanode/formation" has been added to Laravel 4's config.json file.

	"require": {
		"aquanode/formation": "dev-master"
	},

Then run `php composer.phar update` from the command line. Composer will install the Formation package. Now, all you have to do is register the service provider and set up Formation's alias in `app/config/app.php`. Add this to the `providers` array:

	'Aquanode\Formation\FormationServiceProvider',

And add this to the `aliases` array:

	'Form' => 'Aquanode\Formation\Formation',

You may use 'Formation', or another alias, but 'Form' is recommended for the sake of simplicity. Formation is now ready to go.

<a name="opening-form"></a>
## Opening a Form

**Opening a form to POST to the current URL:**

	echo Form::open();

**Opening a form using a given URI and request method:**

	echo Form::open('user/profile', 'PUT');

**Opening a Form that POSTS to an HTTPS URL:**

	echo Form::openSecure('user/profile');

**Specifying extra HTML attributes on a form open tag:**

	echo Form::open('user/profile', 'POST', array('class' => 'awesome'));

**Opening a form that accepts file uploads:**

	echo Form::openForFiles('users/profile');

**Opening a form that accepts file uploads and uses HTTPS:**

	echo Form::openSecureForFiles('users/profile');

**Closing a form:**

	echo Form::close();

<a name="csrf-protection"></a>
## CSRF Protection

Laravel provides an easy method of protecting your application from cross-site request forgeries. First, a random token is placed in your user's session. Don't sweat it, this is done automatically. Next, use the token method to generate a hidden form input field containing the random token on your form:

**Generating a hidden field containing the session's CSRF token:**

	echo Form::token();

**Attaching the CSRF filter to a route:**

	Route::post('profile', array('before' => 'csrf', function()
	{
    	//
	}));

**Retrieving the CSRF token string:**

	$token = Session::token();

> **Note:** You must specify a session driver before using the Laravel CSRF protection facilities. Please see the L4 docs for this.

<a name="default-values"></a>
## Default Form Values

One of the most useful features of Formation is its ability to take an array, object, or Eloquent model and use it to populate form fields automatically. When the form is posted, it will automatically make use of the values in the POSt array instead.

	$defaults = array('name' =>  'Cody Jassman',
					  'email' => 'cody@aquanode.com');
	Form::setDefaults($defaults);

> **Note:** If you want to use array fields names instead, use, for example, `user.name` and `user.email` instead of `name` and `email`.

**Forcing defaults values even after form POST:**

	Form::resetDefaults();

<a name="validation-rules"></a>
## Validation Rules

Formation makes use Laravel 4's Validator class. Using `Form::setValidation()` will create an instance of the Validator class (or many instances if array field names are used in the form setup). The reason the form's validation rules are passed through Formation to Validator is because Formation automatically adds an "error" class to the label and form field if an error is triggered. To do this, Formation needs a copy of the validation rules that have been set.

	$rules = array('user.name' => 'required', //'user.name' can be used for an array field like "user[name]"
				   'email' =>     'required|email');
	Form::setValidationRules($rules);

<a name="labels"></a>
## Labels

**Setting up labels with an array:**

	$labels = array('name' =>  'Name',
					'email' => 'Email Address');
	Form::setLabels($labels);

By setting up your labels with an array, you will be able to leave the second argument `null` in `Form::label()`.

**Generating a label element:**

	echo Form::label('email', 'Email Address');

If you do not pass a label for the second argument, it will be checked for in Formation's `$labels` array that can be set with `Form::setLabels()`. If it is not found here, it will be inferred from the field name in the first argument.

**Specifying extra HTML attributes for a label:**

	echo Form::label('email', 'E-Mail Address', array('class' => 'awesome'));

> **Note:** After creating a label, any form element you create with a name matching the label name will automatically receive an ID matching the label name as well.

<a name="full-array-setup"></a>
## Full Array Setup for Labels, Validation Rules, and Default Values

Setting up labels, validation rules, and default values all at once:

	$form = array(
		'user.name'=>    array('Name', 'required', 'Cody Jassman'),
		'user.website'=> array('Website', '', 'http://'),
		'user.about'=>   array('About You'),
		'user.number'=>  array('Some Sort of Number'),
	);
	Form::setup($form);

<a name="basic-fields"></a>
## Text, Text Area, Password & Hidden Fields

**Generate a text input element:**

	echo Form::text('username');

**Explicitly specifying a default value for a text input element:**

	echo Form::text('email', 'example@gmail.com');

By using `Form::setDefaults()`, you will not need to pass a default value and can instead pass a `null` value or none at all as the second argument to let the field take advantage of the preset default value. When a form is posted, the values in the POST array will be used instead unless `Form::resetDefaults()` is used.

> **Note:** A field with a name attribute "first_name" is automatically given an ID of "first-name". Underscores in names are always replaced with dashes in a field's ID.

**Generating a password input element:**

	echo Form::password('password');

<a name="checkbox-radio"></a>
## Checkboxes and Radio Buttons

**Generating a checkbox input element:**

	echo Form::checkbox('name', 'value');

**Generating a checkbox that is checked by default:**

	echo Form::checkbox('name', 'value', true);

Please keep in mind that once again you will not need the third argument if you set up your default values with `Form::setDefaults()`.

> **Note:** The radio method has the same signature as the checkbox method. Two for one!

<a name="checkbox-radio-sets"></a>
## Checkbox and Radio Button Sets

**Creating a set of checkboxes:**

	$checkboxes = array('rain', 'thunder', 'lightning')
	echo Form::checkboxSet($checkboxes);

**Adding a prefix to the name of each checkbox:**

	echo Form::checkboxSet($checkboxes, 'checkbox');

**Adding attributes to checkboxes and/or unordered list container for checkboxes:

	echo Form::checkboxSet($checkboxes, null, array('class' => 'awesome', 'id_container' => 'checkbox-set-weather'));

> **Note:** Attributes ending with "_container" will be added to the container itself rather than to each of the checkboxes.

**Creating a set of radio buttons:**

	$options = array('rain', 'thunder', 'lightning');
	echo Form::radioSet('weather', $options);

You may append "_container" to add attributes to the container for radio button sets as well.

<a name="drop-down-lists"></a>
## Drop-Down Lists

**Generating a drop-down list from an array of items:**

	echo Form::select('size', array('L' => 'Large', 'S' => 'Small'));

**Using a label with a null value as the first option:**

	echo Form::select('size', array('L' => 'Large', 'S' => 'Small'), 'Select a size');

**Generating a drop-down list with an item selected by default:**

	echo Form::select('size', array('L' => 'Large', 'S' => 'Small'), 'S');

Of course, you may use `Form::setDefaults()` to populate select boxes without the need for the third argument.

**Turn an array, object, or Eloquent model into a set of options:**

	$users = DB::table('users')->orderBy('username')->get();
	echo Form::select('user', Form::prepOptions($users, array('id', 'username')), 'Select a user');

**Turn a simple array into an options array with values the same as its labels:**

	echo Form::select('animal', Form::simpleOptions(array('Tiger', 'Zebra', 'Elephant')), 'Select an animal');

**Turn a simple array into a simple options array with numeric values that do start at one instead of zero:**

	echo Form::select('animal', Form::offsetOptions(array('Tiger', 'Zebra', 'Elephant')), 'Select an animal');

**Turn a simple array into a simple options array with numeric values that start at one instead of zero:**

	echo Form::select('number', Form::numberOptions(0, 180, 10));

The first argument is the starting number, the second is the ending number, and the third is the number to iterate by. If it is negative, you may count down instead of up. Finally, the fourth argument is used to denote the number of decimal places the numbers should have.

> **Note:** All of the above options array building functions can also be used for checkbox sets and radio buttons sets.

**Using field macro for a set of radio buttons:**

	$options = Form::simpleOptions(array('T-Rex', 'Parasaurolophus', 'Triceratops'));
	echo Form::field('dinosaur', 'Favorite Dinosaur', 'radio-set', $options);

<a name="file-input"></a>
## File Input

**Generate a file input element:**

	echo Form::file('image');

<a name="buttons"></a>
## Buttons

**Generating a submit button element:**

	echo Form::submit('Click Me!');

If you do not set the first argument, "Submit" will be used as the label.

> **Note:** Need to create a button element? Try the button method. It has the same signature as submit.

<a name="field-macro"></a>
## Field Macro

You may use the built-in `Form::field()` macro to turn this:

	<div class="field">
		<?php echo Form::label('user.email');
		echo Form::text('user.email');
		echo Form::error('user.email'); ?>
	</div>

Into this:

	echo Form::field('user.email');

The field container element can be changed from a div to another HTML element and the "field" class can be changed as well in `config.php`.

**Using field macro for a drop-down select box:**

	echo Form::field('animal', null, 'select', Form::simpleOptions(array('Tiger', 'Zebra', 'Elephant')));

**Using field macro for a set of radio buttons:**

	$options = Form::simpleOptions(array('T-Rex', 'Parasaurolophus', 'Triceratops'));
	echo Form::field('dinosaur', 'Favorite Dinosaur', 'radio-set', $options);

**Using field macro for a set of checkboxes:**

	echo Form::field('number.', null, 'checkbox-set', Form::offsetOptions(array('One', 'II', '3.0')));

<a name="custom-macros"></a>
## Custom Macros

It's easy to define your own custom Form class helpers called "macros". Here's how it works. First, simply register the macro with a given name and a Closure:

**Registering a Form macro:**

	Form::macro('myField', function()
	{
		return '<input type="awesome" />';
	});

Now you can call your macro using its name:

**Calling a custom Form macro:**

	echo Form::myField();