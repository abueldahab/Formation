<?php namespace Aquanode\Formation;

/*----------------------------------------------------------------------------------------------------------
	Formation
		A powerful form creation composer package for Laravel 4 built on top of Laravel 3's Form class.

		created by Cody Jassman / Aquanode - http://aquanode.com
		last updated on January 21, 2013
----------------------------------------------------------------------------------------------------------*/

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class Formation {

	/**
	 * All of the default values for form fields.
	 *
	 * @var array
	 */
	public static $defaults = array();

	/**
	 * All of the labels for form fields.
	 *
	 * @var array
	 */
	public static $labels = array();

	/**
	 * All of the validation rules (routed through Formation's validation() method to Validator library to allow
	 * automatic addition of error classes to labels and fields).
	 *
	 * @var array
	 */
	public static $validation = array();

	/**
	 * Whether form fields are being reset to their default values rather than the POSTed values.
	 *
	 * @var bool
	 */
	public static $reset = false;

	/**
	 * The registered custom macros.
	 *
	 * @var array
	 */
	public static $macros = array();

	/**
	 * Cache application encoding locally to save expensive calls to config::get().
	 *
	 * @var string
	 */
	public static $encoding = null;

	/**
	 * Registers a custom macro.
	 *
	 * @param  string   $name
	 * @param  Closure  $macro
	 * @return void
	 */
	public static function macro($name, $macro)
	{
		static::$macros[$name] = $macro;
	}

	/**
	 * Assigns default values to form fields.
	 *
	 * @param  array    $defaults
	 * @return void
	 */
	public static function setDefaults($defaults = array())
	{
		//turn Eloquent instances into an array
		if (isset($defaults) && isset($defaults->table) && isset($defaults->timestamps)) $defaults = $defaults->toArray();

		//turn object into array
		if (is_object($defaults)) $defaults = (array) $defaults;

		static::$defaults = $defaults;

		return static::$defaults;
	}

	/**
	 * Resets form field values back to defaults and ignores POSTed values.
	 *
	 * @param  array    $defaults
	 * @return void
	 */
	public static function resetDefaults($defaults = array())
	{
		if (!empty($defaults)) static::setDefaults($defaults); //if new defaults are set, pass them to static::$defaults
		static::$reset = true;
	}

	/**
	 * Assigns labels to form fields.
	 *
	 * @param  array    $labels
	 * @return void
	 */
	public static function setLabels($labels = array())
	{
		if (is_object($labels)) $labels = (array) $labels;
		static::$labels = $labels;
	}

	/**
	 * Route Validator validation rules through Formation to allow Formation
	 * to automatically add error classes to labels and fields.
	 *
	 * @param  array    $rules
	 * @return array
	 */
	public static function setValidationRules($rules = array())
	{
		$rulesFormatted = array();
		foreach ($rules as $name=>$rulesItem) {
			$rulesArray = explode('.', $name);
			if (count($rulesArray) < 2) {
				$rulesFormatted['root'][$rulesArray[(count($rulesArray) - 1)]] = $rulesItem;
			} else {
				$rulesFormatted[$rulesArray[(count($rulesArray) - 2)]][$rulesArray[(count($rulesArray) - 1)]] = $rulesItem;
			}
		}

		foreach ($rulesFormatted as $name=>$rules) {
			if ($name == "root") {
				static::$validation['root'] = Validator::make(Input::all(), $rules);
			} else {
				$data = Input::get($name);
				if (is_null($data)) $data = array();
				static::$validation[$name] = Validator::make($data, $rules);
			}
		}

		return static::$validation;
	}

	/**
	 * Check if one or all Validator instances are valid.
	 *
	 * @param  string   $index
	 * @return bool
	 */
	public static function validated($index = null)
	{
		//if index is null, cycle through all Validator instances
		if (is_null($index)) {
			foreach (static::$validation as $fieldName => $validation) {
				if ($validation->fails()) return false;
			}
		} else {
			if (isset(static::$validation[$index])) {
				if (static::$validation[$index]->fails()) return false;
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Set up whole form with one big array
	 *
	 * @param  array    $form
	 * @return array
	 */
	public static function setup($form = array())
	{
		$labels = array();
		$rules = array();
		$defaults = array();

		if (is_object($form)) $form = (array) $form;
		foreach ($form as $name=>$field) {
			if (is_object($field)) $field = (array) $field;
			if (isset($field[0]) && !is_null($field[0]) && $field[0] != "") $labels[$name] = $field[0];
			if (isset($field[1]) && !is_null($field[1]) && $field[1] != "") $rules[$name] = $field[1];
			if (isset($field[2]) && !is_null($field[2]) && $field[2] != "") $defaults[$name] = $field[2];
		}

		static::setLabels($labels);
		static::setValidationRules($rules);
		static::setDefaults($defaults);

		return static::$validation;
	}

	/**
	 * Open an HTML form.
	 *
	 * <code>
	 *		// Open a "POST" form to the current request URI
	 *		echo Form::open();
	 *
	 *		// Open a "POST" form to a given URI
	 *		echo Form::open('user/profile');
	 *
	 *		// Open a "PUT" form to a given URI
	 *		echo Form::open('user/profile', 'put');
	 *
	 *		// Open a form that has HTML attributes
	 *		echo Form::open('user/profile', 'post', array('class' => 'profile'));
	 * </code>
	 *
	 * @param  string   $action
	 * @param  string   $method
	 * @param  array    $attributes
	 * @param  bool     $https
	 * @return string
	 */
	public static function open($action = null, $method = 'POST', $attributes = array(), $https = null)
	{
		$method = strtoupper($method);

		$attributes['method'] =  static::method($method);

		$attributes['action'] = static::action($action, $https);

		// If a character encoding has not been specified in the attributes, we will
		// use the default encoding as specified in the application configuration
		// file for the "accept-charset" attribute.
		if ( ! array_key_exists('accept-charset', $attributes))
		{
			$attributes['accept-charset'] = Config::get('application.encoding');
		}

		$append = '';

		// Since PUT and DELETE methods are not actually supported by HTML forms,
		// we'll create a hidden input element that contains the request method
		// and set the actual request method variable to POST.
		if ($method == 'PUT' or $method == 'DELETE')
		{
			$append = static::hidden(Request::spoofer, $method);
		}

		$html = '<form'.static::attributes($attributes).'>'.$append . "\n";
		if (Config::get('formation::autoCsrfToken')) {
			$html .= static::token();
		}
		return $html;
	}

	/**
	 * Determine the appropriate request method to use for a form.
	 *
	 * @param  string  $method
	 * @return string
	 */
	protected static function method($method)
	{
		return ($method !== 'GET') ? 'POST' : $method;
	}

	/**
	 * Determine the appropriate action parameter to use for a form.
	 *
	 * If no action is specified, the current request URI will be used.
	 *
	 * @param  string   $action
	 * @param  bool     $https
	 * @return string
	 */
	protected static function action($action = null, $https = false)
	{
		$uri = (is_null($action)) ? URI::current() : $action;

		return static::entities(URL::to($uri, $https));
	}

	/**
	 * Open an HTML form with a HTTPS action URI.
	 *
	 * @param  string  $action
	 * @param  string  $method
	 * @param  array   $attributes
	 * @return string
	 */
	public static function openSecure($action = null, $method = 'POST', $attributes = array())
	{
		return static::open($action, $method, $attributes, true);
	}

	/**
	 * Open an HTML form that accepts file uploads.
	 *
	 * @param  string  $action
	 * @param  string  $method
	 * @param  array   $attributes
	 * @param  bool    $https
	 * @return string
	 */
	public static function openForFiles($action = null, $method = 'POST', $attributes = array(), $https = null)
	{
		$attributes['enctype'] = 'multipart/form-data';

		return static::open($action, $method, $attributes, $https);
	}

	/**
	 * Open an HTML form that accepts file uploads with a HTTPS action URI.
	 *
	 * @param  string  $action
	 * @param  string  $method
	 * @param  array   $attributes
	 * @return string
	 */
	public static function openSecureForFiles($action = null, $method = 'POST', $attributes = array())
	{
		return static::openForFiles($action, $method, $attributes, true);
	}

	/**
	 * Close an HTML form.
	 *
	 * @return string
	 */
	public static function close()
	{
		return '</form>';
	}

	/**
	 * Generate a hidden field containing the current CSRF token.
	 *
	 * @return string
	 */
	public static function token()
	{
		return static::input('hidden', Config::get('formation::csrfToken'), Session::getToken());
	}

	/**
	 * Get the value of the form field. If no POST data exists or reinitialize() has been called, default value
	 * will be used. Otherwise, POST value will be used. Using "checkbox" type ensures a boolean return value
	 *
	 * @param  string  $name
	 * @param  string  $type
	 * @return mixed
	 */
	public static function value($name, $type = 'standard')
	{
		$value = "";
		if (isset(static::$defaults[$name]))	$value = static::$defaults[$name];
		if ($_POST && !static::$reset)			$value = Input::get($name);

		if ($type == "checkbox" && is_null($value)) $value = 0; //if type is "checkbox", use 0 for null values - this helps when using Form::value() to add values to an insert or update query

		return $value;
	}

	/**
	 * Format array named form fields from strings with period notation for arrays ("data.id" = "data[id]")
	 *
	 * @param  string  $name
	 * @return string
	 */
	protected static function name($name)
	{
		$nameArray = explode('.', $name);
		if (count($nameArray) < 2) return $name;

		$nameFormatted = $nameArray[0];
		for ($n=1; $n < count($nameArray); $n++) {
			$nameFormatted .= '['.$nameArray[$n].']';
		}
		return $nameFormatted;
	}

	/**
	 * Create an HTML label element.
	 *
	 * <code>
	 *		// Create a label for the "email" input element
	 *		echo Form::label('email', 'E-Mail Address');
	 * </code>
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function label($name = null, $label = null, $attributes = array())
	{
		$attributes = static::addErrorClass($name, $attributes);

		if (!is_null($name) && $name != "") {
			if (is_null($label)) $label = static::nameToLabel($name);

			//save label in labels array
			static::$labels[$name] = $label;

			$name = static::id($name); //get ID of field for label's "for" attribute
			$attributes['for'] = $name;
		} else {
			if (is_null($label)) $label = "";
		}

		$attributes = static::attributes($attributes);

		$label = static::entities($label);

		return '<label'.$attributes.'>'.$label.'</label>' . "\n";
	}

	/**
	 * Create an HTML label element.
	 *
	 * <code>
	 *		// Create a label for the "email" input element
	 *		echo Form::label('email', 'E-Mail Address');
	 * </code>
	 *
	 * @param  string  $name
	 * @return string
	 */
	protected static function nameToLabel($name)
	{
		$nameArray = explode('.', $name);
		if (count($nameArray) < 2) {
			return ucwords(str_replace('_', ' ', $name));
		} else { //if field is an array, create label from last array index
			return ucwords(str_replace('_', ' ', $nameArray[(count($nameArray) - 1)]));
		}
	}

	/**
	 * Determine the ID attribute for a form element.
	 *
	 * @param  string  $name
	 * @param  array   $attributes
	 * @return string
	 */
	protected static function id($name, $attributes = array())
	{
		// If an ID has been explicitly specified in the attributes, we will
		// use that ID. Otherwise, we will look for an ID in the array of
		// label names so labels and their elements have the same ID.
		if (array_key_exists('id', $attributes)) {
			return $attributes['id'];
		} else {
			//replace array denoting periods and underscores with dashes
			return str_replace('.', '-', str_replace('_', '-', $name));
		}
	}

	/**
	 * Build a list of HTML attributes from an array.
	 *
	 * @param  array   $attributes
	 * @return string
	 */
	public static function attributes($attributes)
	{
		$html = array();

		foreach ((array) $attributes as $key => $value)
		{
			// For numeric keys, we will assume that the key and the value are the
			// same, as this will convert HTML attributes such as "required" that
			// may be specified as required="required", etc.
			if (is_numeric($key)) $key = $value;

			if ( ! is_null($value))
			{
				$html[] = $key.'="'.static::entities($value).'"';
			}
		}

		return (count($html) > 0) ? ' '.implode(' ', $html) : '';
	}

	/**
	 * Convert HTML characters to entities.
	 *
	 * The encoding specified in the application configuration file will be used.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function entities($value)
	{
		return htmlentities($value, ENT_QUOTES, static::encoding(), false);
	}

	/**
	 * Create a field along with a label and error message (if one is set).
	 *
	 * @param  string  $name
	 * @param  string  $type
	 * @return string
	 */
	public static function field($name, $label = null, $type = 'text', $options = array())
	{
		if (is_null($label)) $label = static::nameToLabel($name);

		$html = '<'.Config::get('formation::fieldContainer').' class="'.Config::get('formation::fieldContainerClass').'">' . "\n";
		switch ($type) {
			case "text":
				$html .= static::label($name, $label) . "\n";
				$html .= static::text($name) . "\n";
				break;
			case "textarea":
				$html .= static::label($name, $label) . "\n";
				$html .= static::textarea($name) . "\n";
				break;
			case "select":
				$html .= static::label($name, $label) . "\n";
				$html .= static::select($name, $options) . "\n";
				break;
			case "checkbox":
				$html .= static::checkbox($name) . "\n";
				$html .= static::label($name, $label) . "\n";
				break;
			case "radio":
				$html .= static::radio($name) . "\n";
				$html .= static::label($name, $label) . "\n";
				break;
			case "checkbox-set":
				//for checkbox set, use options as array of checkbox names
				$html .= static::label(null, $label) . "\n";
				$html .= static::checkboxSet($options, $name) . "\n";
				break;
			case "radio-set":
				$html .= static::label(null, $label) . "\n";
				$html .= static::radioSet($name, $options) . "\n";
				break;
		}
		$html .= static::error($name) . "\n";
		$html .= '</div>' . "\n";
		return $html;
	}

	/**
	 * Create an HTML input element.
	 *
	 * <code>
	 *		// Create a "text" input element named "email"
	 *		echo Form::input('text', 'email');
	 *
	 *		// Create an input element with a specified default value
	 *		echo Form::input('text', 'email', 'example@gmail.com');
	 * </code>
	 *
	 * @param  string  $type
	 * @param  string  $name
	 * @param  mixed   $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function input($type, $name, $value = null, $attributes = array())
	{ 
		$name = (isset($attributes['name'])) ? $attributes['name'] : $name;
		$attributes = static::addErrorClass($name, $attributes);

		$id = static::id($name, $attributes);
		if ($type == "hidden" && $id == "" && !isset($attributes['id'])) $id = str_replace('_', '-', $name);

		if (is_null($value) && $type != "password") $value = static::value($name);

		$name = static::name($name);

		$attributes = array_merge($attributes, compact('type', 'name', 'value', 'id'));

		return '<input'.static::attributes($attributes).'>' . "\n";
	}

	/**
	 * Create an HTML text input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function text($name, $value = null, $attributes = array())
	{
		return static::input('text', $name, $value, $attributes);
	}

	/**
	 * Create an HTML password input element.
	 *
	 * @param  string  $name
	 * @param  array   $attributes
	 * @return string
	 */
	public static function password($name, $attributes = array())
	{
		return static::input('password', $name, null, $attributes);
	}

	/**
	 * Create an HTML hidden input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function hidden($name, $value = null, $attributes = array())
	{
		return static::input('hidden', $name, $value, $attributes);
	}

	/**
	 * Create an HTML search input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function search($name, $value = null, $attributes = array())
	{
		return static::input('search', $name, $value, $attributes);
	}

	/**
	 * Create an HTML email input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function email($name, $value = null, $attributes = array())
	{
		return static::input('email', $name, $value, $attributes);
	}

	/**
	 * Create an HTML telephone input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function telephone($name, $value = null, $attributes = array())
	{
		return static::input('tel', $name, $value, $attributes);
	}

	/**
	 * Create an HTML URL input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function url($name, $value = null, $attributes = array())
	{
		return static::input('url', $name, $value, $attributes);
	}

	/**
	 * Create an HTML number input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function number($name, $value = null, $attributes = array())
	{
		return static::input('number', $name, $value, $attributes);
	}

	/**
	 * Create an HTML date input element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function date($name, $value = null, $attributes = array())
	{
		return static::input('date', $name, $value, $attributes);
	}

	/**
	 * Create an HTML file input element.
	 *
	 * @param  string  $name
	 * @param  array   $attributes
	 * @return string
	 */
	public static function file($name, $attributes = array())
	{
		return static::input('file', $name, null, $attributes);
	}

	/**
	 * Create an HTML textarea element.
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function textarea($name, $value = null, $attributes = array())
	{
		$attributes['name'] = $name;
		$attributes['id'] = static::id($name, $attributes);

		$attributes = static::addErrorClass($name, $attributes);

		if (is_null($value)) $value = static::value($name);
		if (is_null($value)) $value = ''; //if value is still null, set it to an empty string

		$attributes['name'] = static::name($attributes['name']);

		return '<textarea'.static::attributes($attributes).'>'.static::entities($value).'</textarea>' . "\n";
	}

	/**
	 * Create an HTML select element.
	 *
	 * <code>
	 *		// Create a HTML select element filled with options
	 *		echo Form::select('sizes', array('S' => 'Small', 'L' => 'Large'));
	 *
	 *		// Create a select element with a default selected value
	 *		echo Form::select('sizes', array('S' => 'Small', 'L' => 'Large'), 'Select a size', 'L');
	 * </code>
	 *
	 * @param  string  $name
	 * @param  array   $options
	 * @param  string  $default
	 * @param  string  $selected
	 * @param  array   $attributes
	 * @return string
	 */
	public static function select($name, $options = array(), $default = null, $selected = null, $attributes = array())
	{
		if (!isset($attributes['id'])) $attributes['id'] = static::id($name, $attributes);
		$attributes['name'] = $name;
		$attributes = static::addErrorClass($name, $attributes);

		if (is_null($selected)) $selected = static::value($name);

		$html = array();

		if (!is_null($default)) $html[] = static::option('', $default, $selected);
		foreach ($options as $value => $display) {
			if (is_array($display)) {
				$html[] = static::optgroup($display, $value, $selected);
			} else {
				$html[] = static::option($value, $display, $selected);
			}
		}

		$attributes['name'] = static::name($attributes['name']);

		return '<select'.static::attributes($attributes).'>'.implode("\n", $html). "\n" .'</select>' . "\n";
	}

	/**
	 * Create an HTML select element optgroup.
	 *
	 * @param  array   $options
	 * @param  string  $label
	 * @param  string  $selected
	 * @return string
	 */
	protected static function optgroup($options, $label, $selected)
	{
		$html = array();

		foreach ($options as $value => $display) {
			$html[] = static::option($value, $display, $selected);
		}

		return '<optgroup label="'.static::entities($label).'">'.implode('', $html).'</optgroup>';
	}

	/**
	 * Create an HTML select element option.
	 *
	 * @param  string  $value
	 * @param  string  $display
	 * @param  string  $selected
	 * @return string
	 */
	protected static function option($value, $display, $selected)
	{
		if (is_array($selected)) {
			$selected = (in_array($value, $selected)) ? 'selected' : null;
		} else {
			$selected = ((string) $value == (string) $selected) ? 'selected' : null;
		}
		$attributes = array('value' => static::entities($value), 'selected' => $selected);

		return '<option'.static::attributes($attributes).'>'.static::entities($display).'</option>';
	}

	/**
	 * Create a set of HTML checkboxes.
	 *
	 * @param  array   $names
	 * @param  string  $name_prefix
	 * @param  array   $attributes
	 * @return string
	 */
	public static function checkboxSet($names = array(), $name_prefix = null, $attributes = array())
	{
		if (!empty($names) && is_array($names)) {
			$containerAttributes = array('class'=> 'checkbox-set');
			foreach ($attributes as $attribute => $value) {

				//appending "_container" to attributes means they apply to the
				//"checkbox-set" container rather than to the checkboxes themselves
				if (substr($attribute, -10) == "_container") {
					if (str_replace('_container', '', $attribute) == "class") {
						$containerAttributes['class'] .= ' '.$value;
					} else {
						$containerAttributes[str_replace('_container', '', $attribute)] = $value;
					}
					unset($attributes[$attribute]);
				}
			}
			$html = '<ul'.static::attributes($containerAttributes).'>';

			foreach ($names as $name=>$display) {
				if (!is_null($name_prefix)) $name = $name_prefix . $name;

				$value = 1;
				if ($value == static::value($name)) {
					$checked = true;
				} else {
					$checked = false;
				}

				//add selected class to list item if checkbox is checked to allow styling for selected checkboxes in set
				$listItemAttributes = array();
				if ($checked) $listItemAttributes['class'] = "selected";
				$li = '<li'.static::attributes($listItemAttributes).'>';

				$attributes['id'] = static::id($name);
				$name = static::name($name);

				$li .= static::checkbox($name, $value, $checked, $attributes);
				$li .= static::label($attributes['id'], $display);

				$li .= '</li>';
				$html .= $li;
			}

			$html .= '</ul>' . "\n";
			return $html;
		}
	}

	/**
	 * Create an HTML checkbox input element.
	 *
	 * <code>
	 *		// Create a checkbox element
	 *		echo Form::checkbox('terms', 'yes');
	 *
	 *		// Create a checkbox that is selected by default
	 *		echo Form::checkbox('terms', 'yes', true);
	 * </code>
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  bool    $checked
	 * @param  array   $attributes
	 * @return string
	 */
	public static function checkbox($name, $value = 1, $checked = false, $attributes = array())
	{
		if ($value == static::value($name)) $checked = true;

		$name = static::name($name);

		return static::checkable('checkbox', $name, $value, $checked, $attributes);
	}

	/**
	 * Create a set of HTML radio buttons.
	 *
	 * @param  string  $name
	 * @param  array   $options
	 * @param  string  $selected
	 * @param  array   $attributes
	 * @return string
	 */
	public static function radioSet($name, $options = array(), $selected = null, $attributes = array())
	{
		if (!empty($options) && is_array($options)) {
			$containerAttributes = array('class'=> 'radio-set');
			foreach ($attributes as $attribute => $value) {

				//appending "_container" to attributes means they apply to the
				//"radio-set" container rather than to the checkboxes themselves
				if (substr($attribute, -10) == "_container") {

					if (str_replace('_container', '', $attribute) == "class") {
						$containerAttributes['class'] .= ' '.$value;
					} else {
						$containerAttributes[str_replace('_container', '', $attribute)] = $value;
					}
					unset($attributes[$attribute]);
				}
			}
			$containerAttributes = static::addErrorClass($name, $containerAttributes);
			$html = '<ul'.static::attributes($containerAttributes).'>';

			$label = static::label($name, $name); //set dummy label so ID can be created in line below
			$idPrefix = static::id($name, $attributes);

			if (is_null($selected)) $selected = static::value($name);
			foreach ($options as $value => $display) {
				if ($selected == $value) {
					$checked = true;
				} else {
					$checked = false;
				}

				//add selected class to list item if checkbox is checked to allow styling for selected checkboxes in set
				$listItemAttributes = array();
				if ($checked) $listItemAttributes['class'] = "selected";
				$li = '<li'.static::attributes($listItemAttributes).'>';

				//append radio button value to the end of ID to prevent all radio buttons from having the same ID
				$attributes['id'] = $idPrefix.'-'.str_replace(' ', '-', str_replace('_', '-', strtolower($value)));

				$li .= static::radio($name, $value, $checked, $attributes);
				$li .= static::label($attributes['id'], $display);

				$li .= '</li>';
				$html .= $li;
			}

			$html .= '</ul>' . "\n";
			return $html;
		}
	}

	/**
	 * Create an HTML radio button input element.
	 *
	 * <code>
	 *		// Create a radio button element
	 *		echo Form::radio('drinks', 'Milk');
	 *
	 *		// Create a radio button that is selected by default
	 *		echo Form::radio('drinks', 'Milk', true);
	 * </code>
	 *
	 * @param  string  $name
	 * @param  string  $value
	 * @param  bool    $checked
	 * @param  array   $attributes
	 * @return string
	 */
	public static function radio($name, $value = null, $checked = false, $attributes = array())
	{
		if (is_null($value)) $value = $name;
		if ($value == static::value($name)) $checked = true;

		$name = static::name($name);

		return static::checkable('radio', $name, $value, $checked, $attributes);
	}

	/**
	 * Create a checkable input element.
	 *
	 * @param  string  $type
	 * @param  string  $name
	 * @param  string  $value
	 * @param  bool    $checked
	 * @param  array   $attributes
	 * @return string
	 */
	protected static function checkable($type, $name, $value, $checked, $attributes)
	{
		if ($checked) $attributes['checked'] = 'checked';

		$attributes['id'] = static::id($name, $attributes);
		if (is_null($attributes['id'])) $attributes['id'] = str_replace('_', '-', $name);

		return static::input($type, $name, $value, $attributes);
	}

	/**
	 * Prepare an options array from a database object or other complex
	 * object/array for a select field, checkbox set, or radio button set.
	 *
	 * @param  array   $options
	 * @param  array   $vars
	 * @return array
	 */
	public static function prepOptions($options = array(), $vars = array())
	{
		$optionsFormatted = array();

		//turn Eloquent instances into an array
		if (isset($options[0]) && isset($options[0]->table) && isset($options[0]->timestamps)) $options = $options->toArray();

		if (is_string($vars) || (is_array($vars) && count($vars) > 0)) {
			foreach ($options as $option) {
				//turn object into array
				if (is_object($option)) $option = (array) $option;

				if (is_string($vars)) {
					$label = false;
					$value = $vars;
				} else if (is_array($vars) && count($vars) == 1) {
					$label = false;
					$value = $vars[0];
				} else {
					$label = $vars[0];
					$value = $vars[1];
				}

				if ($label) {
					if (isset($option[$label]) && isset($option[$value])) {
						$optionsFormatted[$option[$label]] = $option[$value];
					}
				} else {
					if (isset($option[$value])) {
						$optionsFormatted[$option[$value]] = $option[$value];
					}
				}
			}
		}
		return $optionsFormatted;
	}

	/**
	 * Create an associative array from a simple array for a select field, checkbox set, or radio button set.
	 *
	 * @param  array   $options
	 * @return array
	 */
	public static function simpleOptions($options = array())
	{
		$optionsFormatted = array();
		foreach ($options as $option) {
			$optionsFormatted[$option] = $option;
		}
		return $optionsFormatted;
	}

	/**
	 * Offset a simple array by 1 index to prevent any options from having an
	 * index (value) of 0 for a select field, checkbox set, or radio button set.
	 *
	 * @param  array   $options
	 * @return array
	 */
	public static function offsetOptions($options = array())
	{
		$optionsFormatted = array();
		for ($o=0; $o < count($options); $o++) {
			$optionsFormatted[($o + 1)] = $options[$o];
		}
		return $optionsFormatted;
	}

	/**
	 * Create an options array of numbers within a specified range
	 * for a select field, checkbox set, or radio button set.
	 *
	 * @param  integer $start
	 * @param  integer $end
	 * @param  integer $increment
	 * @param  integer $decimals
	 * @return array
	 */
	public static function numberOptions($start = 1, $end = 10, $increment = 1, $decimals = 0)
	{
		$options = array();
		if (is_numeric($start) && is_numeric($end)) {
			if ($start <= $end) {
				for ($o = $start; $o <= $end; $o += $increment) {
					if ($decimals) {
						$value = number_format($o, $decimals, '.', '');
					} else {
						$value = $o;
					}
					$options[$value] = $value;
				}
			} else {
				for ($o = $start; $o >= $end; $o -= $increment) {
					if ($decimals) {
						$value = number_format($o, $decimals, '.', '');
					} else {
						$value = $o;
					}
					$options[$value] = $value;
				}
			}
		}
		return $options;
	}

	/**
	 * Add an error class to an HTML attributes array if a validation error exists for the specified form field.
	 *
	 * @param  string  $name
	 * @param  array   $attributes
	 * @return array
	 */
	public static function addErrorClass($name, $attributes = array())
	{
		if (static::errorMessage($name)) { //an error exists; add the error class
			if (!isset($attributes['class'])) {
				$attributes['class'] = "error";
			} else {
				$attributes['class'] .= " error";
			}
		}
		return $attributes;
	}

	/**
	 * Create error div for validation error if it exists for specified form field.
	 *
	 * @param  string  $name
	 * @param  bool    $alwaysExists
	 * @return string
	 */
	public static function error($name, $alwaysExists = false)
	{
		$attr = "";
		if ($alwaysExists) $attr = ' id="'.str_replace('_', '-', $name).'-error"';
		$message = static::errorMessage($name);
		if ($message && $message != "") {
			return '<div class="error"'.$attr.'>'.$message.'</div>';
		} else {
			if ($alwaysExists) return '<div class="error"'.$attr.' style="display: none;"></div>';
		}
	}

	/**
	 * Get validation error message if it exists for specified form field. Modified to work with array fields.
	 *
	 * @param  string  $name
	 * @return string
	 */
	public static function errorMessage($name)
	{
		//replace field name in error message with label if it exists
		$nameFormatted = $name;
		if (isset(static::$labels[$name]) && static::$labels[$name] != "") {
			$nameFormatted = static::$labels[$name];
		}

		//cycle through all validation instances to allow the ability to get error messages in root fields
		//as well as field arrays like "field[array]" (passed to errorMessage in the form of "field.array")
		foreach (static::$validation as $fieldName => $validation) {
			$valid = $validation->passes();

			if ($validation->messages()) {
				$messages = $validation->messages();
				$nameArray = explode('.', $name);
				if (count($nameArray) < 2) {
					if ($_POST && $fieldName == "root" && $messages->first($name) != "")
						return str_replace($name, $nameFormatted, $messages->first($name));
				} else {
					$index = 	$nameArray[(count($nameArray) - 2)];
					$index2 =	$nameArray[(count($nameArray) - 1)];
					if ($_POST && $fieldName == $index && $messages->first($index2) != "")
						return str_replace($index2, $nameFormatted, $messages->first($index2));
				}
			}
		}

		return false;
	}

	/**
	 * Create an HTML submit input element.
	 *
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function submit($value = 'Submit', $attributes = array())
	{
		return static::input('submit', null, $value, $attributes);
	}

	/**
	 * Create an HTML reset input element.
	 *
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function reset($value = null, $attributes = array())
	{
		return static::input('reset', null, $value, $attributes);
	}

	/**
	 * Create an HTML image input element.
	 *
	 * <code>
	 *		// Create an image input element
	 *		echo Form::image('img/submit.png');
	 * </code>
	 *
	 * @param  string  $url
	 * @param  string  $name
	 * @param  array   $attributes
	 * @return string
	 */
	public static function image($url, $name = null, $attributes = array())
	{
		$attributes['src'] = URL::to_asset($url);

		return static::input('image', $name, null, $attributes);
	}

	/**
	 * Create an HTML button element.
	 *
	 * @param  string  $value
	 * @param  array   $attributes
	 * @return string
	 */
	public static function button($value = null, $attributes = array())
	{
		return '<button'.static::attributes($attributes).'>'.static::entities($value).'</button>' . "\n";
	}

	/**
	 * Get the appliction.encoding without needing to request it from Config::get() each time.
	 *
	 * @return string
	 */
	protected static function encoding()
	{
		return static::$encoding ?: static::$encoding = Config::get('site.encoding');
	}

	/**
	 * Get an options array of US states.
	 *
	 * @param  bool    $useAbbrev
	 * @return array
	 */
	public static function states($useAbbrev = true)
	{
		$states = array('AL'=>'Alabama', 'AK'=>'Alaska', 'AZ'=>'Arizona', 'AR'=>'Arkansas', 'CA'=>'California', 'CO'=>'Colorado', 'CT'=>'Connecticut', 'DE'=>'Delaware', 'DC'=>'District of Columbia',
						'FL'=>'Florida', 'GA'=>'Georgia', 'HI'=>'Hawaii', 'ID'=>'Idaho', 'IL'=>'Illinois', 'IN'=>'Indiana', 'IA'=>'Iowa', 'KS'=>'Kansas', 'KY'=>'Kentucky', 'LA'=>'Louisiana', 'ME'=>'Maine',
						'MD'=>'Maryland', 'MA'=>'Massachusetts', 'MI'=>'Michigan', 'MN'=>'Minnesota', 'MS'=>'Mississippi', 'MO'=>'Missouri', 'MT'=>'Montana', 'NE'=>'Nebraska', 'NV'=>'Nevada',
						'NH'=>'New Hampshire', 'NJ'=>'New Jersey', 'NM'=>'New Mexico', 'NY'=>'New York', 'NC'=>'North Carolina', 'ND'=>'North Dakota', 'OH'=>'Ohio', 'OK'=>'Oklahoma', 'OR'=>'Oregon',
						'PA'=>'Pennsylvania', 'PR'=>'Puerto Rico', 'RI'=>'Rhode Island', 'SC'=>'South Carolina', 'SD'=>'South Dakota', 'TN'=>'Tennessee', 'TX'=>'Texas', 'UT'=>'Utah', 'VT'=>'Vermont',
						'VA'=>'Virginia', 'VI'=>'Virgin Islands', 'WA'=>'Washington', 'WV'=>'West Virginia', 'WI'=>'Wisconsin', 'WY'=>'Wyoming');
		if ($useAbbrev) {
			return $states;
		} else {
			return explode(',', implode(',', $states)); //remove abbreviation keys
		}
	}

	/**
	 * Get an options array of Canadian provinces.
	 *
	 * @param  bool    $useAbbrev
	 * @return array
	 */
	public static function provinces($useAbbrev = true)
	{
		$provinces = array('AB'=>'Alberta', 'BC'=>'British Columbia', 'MB'=>'Manitoba', 'NB'=>'New Brunswick', 'NL'=>'Newfoundland', 'NT'=>'Northwest Territories', 'NS'=>'Nova Scotia',
						   'NU'=>'Nunavut', 'ON'=>'Ontario', 'PE'=>'Prince Edward Island', 'QC'=>'Quebec', 'SK'=>'Saskatchewan', 'YT'=>'Yukon Territory');
		if ($useAbbrev) {
			return $provinces;
		} else {
			return explode(',', implode(',', $provinces)); //remove abbreviation keys
		}
	}

	/**
	 * Get an options array of countries.
	 *
	 * @return array
	 */
	public static function countries()
	{
		return array('Canada','United States','Afghanistan','Albania','Algeria','American Samoa','Andorra','Angola','Anguilla','Antarctica','Antigua And Barbuda','Argentina','Armenia','Aruba',
					 'Australia','Austria','Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bermuda','Bhutan','Bolivia','Bosnia And Herzegowina',
				 	 'Botswana','Bouvet Island','Brazil','British Indian Ocean Territory','Brunei Darussalam','Bulgaria','Burkina Faso','Burundi','Cambodia','Cameroon','Cape Verde','Cayman Islands',
				 	 'Central African Republic','Chad','Chile','China','Christmas Island','Cocos (Keeling) Islands','Colombia','Comoros','Congo','Congo, The Democratic Republic Of The','Cook Islands',
				 	 'Costa Rica','Cote D\'Ivoire','Croatia (Local Name: Hrvatska)','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominica','Dominican Republic','East Timor','Ecuador','Egypt',
					 'El Salvador','Equatorial Guinea','Eritrea','Estonia','Ethiopia','Falkland Islands (Malvinas)','Faroe Islands','Fiji','Finland','France','France, Metropolitan','French Guiana',
				 	 'French Polynesia','French Southern Territories','Gabon','Gambia','Georgia','Germany','Ghana','Gibraltar','Greece','Greenland','Grenada','Guadeloupe','Guam','Guatemala','Guinea',
				 	 'Guinea-Bissau','Guyana','Haiti','Heard And Mc Donald Islands','Holy See (Vatican City State)','Honduras','Hong Kong','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland',
				 	 'Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Korea, Democratic People\'S Republic Of','Korea, Republic Of','Kuwait','Kyrgyzstan',
				 	 'Lao People\'S Democratic Republic','Latvia','Lebanon','Lesotho','Liberia','Libyan Arab Jamahiriya','Liechtenstein','Lithuania','Luxembourg','Macau','Macedonia, Former Yugoslav Republic Of',
				 	 'Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Martinique','Mauritania','Mauritius','Mayotte','Mexico','Micronesia, Federated States Of',
				 	 'Moldova, Republic Of','Monaco','Mongolia','Montserrat','Morocco','Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','Netherlands Antilles','New Caledonia','New Zealand',
				 	 'Nicaragua','Niger','Nigeria','Niue','Norfolk Island','Northern Mariana Islands','Norway','Oman','Pakistan','Palau','Panama','Papua New Guinea','Paraguay','Peru','Philippines',
				 	 'Pitcairn','Poland','Portugal','Puerto Rico','Qatar','Reunion','Romania','Russian Federation','Rwanda','Saint Kitts And Nevis','Saint Lucia','Saint Vincent And The Grenadines',
				 	 'Samoa','San Marino','Sao Tome And Principe','Saudi Arabia','Senegal','Seychelles','Sierra Leone','Singapore','Slovakia (Slovak Republic)','Slovenia','Solomon Islands','Somalia',
				 	 'South Africa','South Georgia, South Sandwich Islands','Spain','Sri Lanka','St. Helena','St. Pierre And Miquelon','Sudan','Suriname','Svalbard And Jan Mayen Islands','Swaziland',
				 	 'Sweden','Switzerland','Syrian Arab Republic','Taiwan','Tajikistan','Tanzania, United Republic Of','Thailand','Togo','Tokelau','Tonga','Trinidad And Tobago','Tunisia','Turkey',
				 	 'Turkmenistan','Turks And Caicos Islands','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States Minor Outlying Islands','Uruguay','Uzbekistan',
				 	 'Vanuatu','Venezuela','Viet Nam','Virgin Islands (British)','Virgin Islands (U.S.)','Wallis And Futuna Islands','Western Sahara','Yemen','Yugoslavia','Zambia','Zimbabwe');
	}

	/**
	 * Get an options array of times.
	 *
	 * @param  string  $minutes
	 * @param  bool    $useAbbrev
	 * @return array
	 */
	public static function times($minutes = 'half')
	{
		$times = array();
		$minutesOptions = array('00');
		switch ($minutes) {
			case "full":
				$minutesOptions = array('00'); break;
			case "half":
				$minutesOptions = array('00', '30'); break;
			case "quarter":
				$minutesOptions = array('00', '15', '30', '45'); break;
			case "all":
				$minutesOptions = array();
				for ($m=0; $m < 60; $m++) {
					$minutesOptions[] = sprintf('%02d', $m);
				}
				break;
		}

		for ($h=0; $h < 24; $h++) {
			$hour = sprintf('%02d', $h);
			if ($h < 12) { $meridiem = "am"; } else { $meridiem = "pm"; }
			if ($h == 0) $hour = 12;
			if ($h > 12) {
				$hour = sprintf('%02d', ($hour - 12));
			}
			foreach ($minutesOptions as $minutes) {
				$times[sprintf('%02d', $h).':'.$minutes.':00'] = $hour.':'.$minutes.$meridiem;
			}
		}
		return $times;
	}

	/**
	 * Dynamically handle calls to custom macros.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public static function __callStatic($method, $parameters)
	{
		if (isset(static::$macros[$method]))
		{
			return call_user_func_array(static::$macros[$method], $parameters);
		}

		throw new \Exception("Method [$method] does not exist.");
	}

}