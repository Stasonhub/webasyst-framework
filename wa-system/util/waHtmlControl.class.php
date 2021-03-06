<?php

/**
 *
 * @author Webasyst
 *
 */
class waHtmlControl
{
    const INPUT = 'input';
    const FILE = 'file';
    const TEXTAREA = 'textarea';
    const PASSWORD = 'password';
    const RADIOGROUP = 'radiogroup';
    const SELECT = 'select';
    const CHECKBOX = 'checkbox';
    const GROUPBOX = 'groupbox';
    const INTERVAL = 'interval';
    const CONTACT = 'contact';
    const CONTACTFIELD = 'contactfield';
    const HELP = 'help';
    const CUSTOM = 'custom';
    const HIDDEN = 'hidden';

    static private $predefined_controls = array();
    static private $custom_controls = array();
    static private $instance = null;

    private function __construct()
    {
        self::$predefined_controls = array(
            self::INPUT,
            self::FILE,
            self::TEXTAREA,
            self::PASSWORD,
            self::RADIOGROUP,
            self::SELECT,
            self::CHECKBOX,
            self::HIDDEN,
            self::GROUPBOX,
            self::CONTACT,
            self::CONTACTFIELD,
        );
    }

    public static $default_charset = 'utf-8';

    private function __clone()
    {
    }

    /**
     *
     * @return waHtmlControl
     */
    private static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get control html code
     *
     * @throws Exception
     * @param string $type Type of control (use standard or try to found registered control types) (also support raw control type)
     * @param string $name Control name
     * @param array $params
     * @param array [string]mixed $params Control params
     * @param array [string]mixed $params['namespace'] array of string or string with control namespace
     * @param array [string]mixed $params['class'] array of string or string with control CSS class
     * @param array [string]mixed $params['style'] HTML property of control
     * @param array [string]mixed $params['value']
     * @param array [string]mixed $params['size'] HTML property of control
     * @param array [string]mixed $params['maxlength'] HTML property of control
     * @param array [string]mixed $params['cols'] HTML property of control
     * @param array [string]mixed $params['rows'] HTML property of control
     * @param array [string]mixed $params['wrap']
     * @param array [string]mixed $params['callback']
     * @param array [string]array $params['options'] variants for selectable control
     * @param array [string][][string]string $params['options']['title'] variant item title for selectable control
     * @param array [string][][string]mixed $params['options']['value'] variant item value for selectable control
     *
     * @param array [string]string $params['title_wrapper'] title output format format
     * @param array [string]string $params['description_wrapper'] description output format
     * @param array [string]string $params['control_wrapper'] control output format
     * @param array [string]string $params['control_separator'] control items separator
     *
     * @return string
     */
    public static function getControl($type, $name, $params = array())
    {
        if (!is_array($params)) {
            throw new waException('Invalid function params at '.__METHOD__.' expect $params to be array');
        }
        #raw type support
        if (preg_match('/^([\w]+)(\s+.*)$/', $type, $matches)) {
            $type = $matches[1];
            $options = trim($matches[2]);
            switch ($type) {
                case self::CUSTOM:
                    if (preg_match('/^([\w:]+)(.*)$/', $options, $matches)) {
                        $params['callback'] = $matches[1];
                        if (preg_match('/^[\w]+::[\w]+$/', $params['callback'])) {
                            $params['callback'] = explode('::', $params['callback']);
                        }
                        $options = trim($matches[2]);
                    }
                    break;
            }

            #transform raw options data into array
            if (!isset($params['options'])) {
                $params['options'] = self::getControlParams($options);
            }
        } elseif (!empty($params['options_callback']) && !isset($params['options'])) {
            $params['options'] = self::getControlParams($params['options_callback']);
        }


        #input exception handler support
        //TODO check and complete code
        if (isset($params['wrong_value'])) {
            if (false && isset($params['value']) && is_array($params['value']) && is_array($params['wrong_value'])) {
                $params['value'] = array_merge($params['value'], $params['wrong_value']);
            } else {
                $params['value'] = $params['wrong_value'];
            }
        }

        #namespace workaround
        if (isset($params['name'])) {
            $name = $name ? $name : $params['name'];
            unset($params['name']);
        }
        $namespace = self::makeNamespace($params);
        if ($namespace !== null) {
            $name = "{$namespace}[{$name}]";
            unset($params['namespace']);
        }

        #usage dual standard for options value=>title
        if (isset($params['options']) && is_array($params['options'])) {
            foreach ($params['options'] as $key => & $data) {
                if (!is_array($data)) {
                    //TODO check format usage
                    $data = array('title' => $data, 'value' => $key);
                }
                unset($data);
            }
        }

        #type aliases
        switch ($type) {
            case 'text':
                $type = self::INPUT;
                break;
            default:
                break;
        }

        $control_name = "get".ucfirst($type)."Control";
        if (!isset($params['class'])) {
            $params['class'] = array();
        } elseif (!is_array($params['class'])) {
            $params['class'] = array($params['class']);
        }

        $params['class'][] = $type;
        $wrappers = array(
            'title_wrapper'       => '%s:&nbsp;',
            'description_wrapper' => '<br>%s<br>',
            'control_wrapper'     => "%s\n%s\n%s\n",
            'control_separator'   => "<br>",

        );
        $params = array_merge($wrappers, $params);
        self::makeId($params, $name);
        $instance = self::getInstance();
        $control = $instance->$control_name($name, $params);
        if (is_array($control)) {
            $controls = array_values($control);
            $control = '';
            foreach ($controls as $id => $chunk) {
                $control .= sprintf($params['control_wrapper'], $chunk['title'], $chunk['control'], $chunk['description']);
                if ($id < count($controls)) {
                    $control .= $params['control_separator'];
                }
            }
        } else {
            $control = sprintf($params['control_wrapper'], self::getControlTitle($params), $control, self::getControlDescription($params));
            if ($control === false) {
                $control = "Invalid param 'control_wrapper' for {$type}:{$name}";
            }
        }
        return $control;
    }

    /**
     * @param $raw_params string raw control params in CSV format
     * @return array
     */
    private static function getControlParams($raw_params)
    {
        $options = null;
        if (is_array($raw_params)) {
            $callback = array_shift($raw_params);
            if (function_exists($callback)) {
                $options = call_user_func_array($callback, $raw_params);
            } elseif (is_string($callback) && class_exists($callback)) {
                $callback = array($callback);
                $callback[] = array_shift($raw_params);
                if (in_array($callback[1], get_class_methods($callback[0]))) {
                    $options = call_user_func_array($callback, $raw_params);
                }
            } elseif (is_object($callback) && ($class = get_class($callback))) {
                $callback = array($callback);
                $callback[] = array_shift($raw_params);
                if (in_array($callback[1], get_class_methods($class))) {
                    $options = call_user_func_array($callback, $raw_params);
                }
            }
        } elseif (preg_match('/^([_\w]+)::([_\w]+)(\s+.+)?$/', $raw_params, $matches) && class_exists($matches[1]) && in_array($matches[2], get_class_methods($matches[1]))) {
            $callback = array($matches[1], $matches[2]);
            $options = isset($matches[3]) ? call_user_func($callback, $matches[3]) : call_user_func($callback);
        } elseif (preg_match('/([_\w]+)(.*)/', $raw_params, $matches) && function_exists($matches[1])) {
            $options = call_user_func($matches[1], $matches[2]);
        }

        #parse CSV format
        if (!is_array($options) && $raw_params) {
            $csv_pattern = '@(?:,|^)([^",]+|"(?:[^"]|"")*")?@';
            $cell_pattern = '@^"([^"].+[^"])"$@';
            if (preg_match_all($csv_pattern, $raw_params, $matches)) {
                $options = array();
                foreach ($matches[1] as $param) {
                    if (preg_match($cell_pattern, $param, $param_matches)) {
                        $param = $param_matches[1];
                    }
                    $param = str_replace('""', '"', $param);
                    $param = explode(':', $param, 2);
                    $options[] = array('title' => $param[0], 'value' => isset($param[1]) ? $param[1] : $param[0]);
                }
            }
        }
        return $options;
    }

    public function __call($function_name, $args = null)
    {
        if (preg_match('/^get(\w+)Control$/', $function_name, $matches)) {
            $type = $matches[1];
            $name = array_shift($args);
            $params = array_shift($args);
            if (!isset($params['value'])) {
                $params['value'] = isset($params['default']) ? $params['default'] : false;
            }
            if (isset(self::$custom_controls[$type])) {
                return call_user_func_array(self::$custom_controls[$type], array($name, $params));
            } else {
                $message = "Control type <b>{$type}</b> undefined";
                if (self::$custom_controls) {
                    $message .= ", use one of this: ".implode(', ', array_keys(self::$custom_controls));
                }
                return $message;
            }
        } else {
            throw new Exception("Call undefined function {$function_name} at ".__CLASS__);
        }

    }

    /**
     * Register user input control
     *
     * @throws Exception
     * @param string $type
     * @param callback $callback
     * @return void
     */
    public static function registerControl($type, $callback)
    {
        if (is_callable($callback)) {
            self::$custom_controls[$type] = $callback;
        } else {
            throw new Exception("invalid callback for control type {$type}");
        }
    }

    /**
     *
     * @param $params
     * @param $name
     * @param $id
     * @return string
     */
    final public static function makeId(&$params, $name = '', $id = null)
    {
        static $counter = 0;
        //settings_{$name}_{$id}
        $params['id'] = $id ? $id : ((isset($params['id']) && $params['id']) ? $params['id'] : strtolower(__CLASS__));
        if (isset($params['namespace'])) {

        }
        if ($name) {
            $params['id'] .= "_{$name}";
        } elseif ($name === false) {
            $params['id'] .= ++$counter.'_';
        }
        $params['id'] = preg_replace(array('/[_]{2,}/', '/[_]{1,}$/'), array('_', ''), str_replace(array('[', ']'), '_', $params['id']));
    }

    final private static function makeNamespace($params)
    {
        $namespace = null;
        if (!empty($params['namespace'])) {
            if (is_array($params['namespace'])) {
                $namespace = array_shift($params['namespace']);
                while (($namespace_chunk = array_shift($params['namespace'])) !== null) {
                    $namespace .= "[{$namespace_chunk}]";
                }
            } else {
                $namespace = $params['namespace'];
            }
        }
        return $namespace;
    }

    /**
     * Add namespace for control params
     * name="control_name" became name="namespace[control_name]" for string and name="namespace1[namespace2]...[control_name ] for array
     * @param $params array
     * @param $namespace string|array
     * @return void
     */
    final public static function addNamespace(&$params, $namespace = '')
    {
        if (isset($params['namespace'])) {
            if (!is_array($params['namespace'])) {
                $params['namespace'] = array($params['namespace']);
            }
        } else {
            $params['namespace'] = array();
        }
        foreach ((array)$namespace as $chunk) {
            $params['namespace'][] = $chunk;
        }
    }

    public static function getName(&$params, $name = null)
    {
        if (isset($params['name'])) {
            $name = $name ? $name : $params['name'];
            unset($params['name']);
        }
        $namespace = self::makeNamespace($params);
        if ($namespace !== null) {
            $name = "{$namespace}[{$name}]";
            unset($params['namespace']);
        }
        return $name;
    }

    private static function getControlTitle($params)
    {
        $title = '';
        if (isset($params['title']) && !empty($params['title_wrapper'])) {
            $option_title = htmlentities(self::_wp($params['title'], $params), ENT_QUOTES, self::$default_charset);
            if (!empty($params['id'])) {
                $params['id'] = htmlentities($params['id'], ENT_QUOTES, self::$default_charset);
                $option_title = sprintf('<label for="%s">%s</label>', $params['id'], $option_title);
            }
            $title = sprintf($params['title_wrapper'], $option_title);
        } elseif ($params['title_wrapper'] === false) {
            $title = null;
        }
        return $title;
    }

    private static function getControlDescription($params)
    {
        $description = '';
        if (!empty($params['description_wrapper']) && !empty($params['description'])) {
            $description = sprintf($params['description_wrapper'], self::_wp($params['description'], $params));
        }
        return $description;
    }

    private function getInputControl($name, $params = array())
    {
        $control = '';
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $control .= "<input id=\"{$params['id']}\" type=\"text\" name=\"{$control_name}\" ";
        if (isset($params['format_description'])) {
            $params['format_description'] = self::_wp($params['format_description']);
        }
        $map = array(
            'class',
            'style',
            'size',
            'maxlength',
            'value',
            'placeholder',
            'readonly',
            'disabled',
            'autocomplete',
            'autofocus',
            'format'             => 'data-regexp',
            'format_description' => 'data-regexp-hint'
        );
        $control .= self::addCustomParams($map, $params);
        $control .= ">";
        return $control;
    }

    private function getHiddenControl($name, $params = array())
    {
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $control = "<input type=\"hidden\" name=\"{$control_name}\" ";
        $control .= self::addCustomParams(array('class', 'value'), $params);
        $control .= ">";
        return $control;
    }

    private function getFileControl($name, $params = array())
    {
        $control = '';
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $control .= "<input type=\"file\" name=\"{$control_name}\" ";
        $control .= self::addCustomParams(array('class', 'style', 'size', 'maxlength', 'value', 'id'), $params);
        $control .= ">";
        return $control;
    }

    private function getTextareaControl($name, $params = array())
    {
        $control = '';
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $value = htmlentities((string)$params['value'], ENT_QUOTES, self::$default_charset);
        $control .= "<textarea name=\"{$control_name}\"";
        $control .= self::addCustomParams(array('class', 'style', 'cols', 'rows', 'wrap', 'id', 'placeholder', 'readonly', 'autofocus', 'disabled'), $params);
        $control .= ">{$value}</textarea>";

        if (empty($params['wysiwyg']) && !empty($params['wisywig'])) {
            $params['wysiwyg'] = $params['wisywig'];
        }

        if (!empty($params['wysiwyg'])) {
            if (!is_array($params['wysiwyg'])) {
                $params['wysiwyg'] = array();
            }
            $params['wysiwyg'] += array(
                'mode'         => 'text/html',
                'tabMode'      => 'indent',
                'height'       => 'dynamic',
                'lineWrapping' => 'true',
            );
            $options = json_encode($params['wysiwyg']);
            $control .= <<<HTML
<style type="text/css">
    .CodeMirror {
        border: 1px solid #ABADB3;
    }
</style>
<script type="text/javascript">
    if(typeof(CodeMirror) == 'function') {
        setTimeout(function(){
            CodeMirror.fromTextArea(document.getElementById('{$params['id']}'), {$options});
        },500);
    }
</script>
HTML;
        }

        return $control;
    }

    private function getHelpControl($name, $params = array())
    {
        $control = '';
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $value = htmlentities((string)$params['value'], ENT_QUOTES, self::$default_charset);
        $control .= "<p name=\"{$control_name}\"";
        $control .= self::addCustomParams(array('class', 'style',), $params);
        $control .= ">{$value}</p>";
        return $control;
    }

    /**
     * @todo hide real value for password
     * @param $name
     * @param $params
     * @return string
     */
    private function getPasswordControl($name, $params = array())
    {
        $control = '';
        $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
        $control .= "<input type=\"password\" name=\"{$control_name}\"";
        $control .= self::addCustomParams(array('class', 'style', 'size', 'maxlength', 'value', 'id', 'placeholder', 'readonly', 'autofocus', 'disabled'), $params);
        $control .= ">";
        return $control;
    }

    private function getRadiogroupControl($name, $params = array())
    {

        $control = '';
        $id = 0;
        $value = htmlentities((string)$params['value'], ENT_QUOTES, self::$default_charset);
        $options = isset($params['options']) ? (is_array($params['options']) ? $params['options'] : array($params['options'])) : array();
        foreach ($options as $option) {
            ++$id;
            $option_value = $option['value'];
            if ($option_value == $value) {
                $params['checked'] = 'checked';
            } elseif (isset($params['checked'])) {
                unset($params['checked']);
            }
            self::makeId($params, $name, md5($option_value));
            $option_value = htmlentities((string)$option_value, ENT_QUOTES, self::$default_charset);
            $control_name = htmlentities($name, ENT_QUOTES, self::$default_charset);
            $control .= "<input type=\"radio\" name=\"{$control_name}\" value=\"{$option_value}\"";
            $control .= self::addCustomParams(array('class', 'style', 'id', 'checked', 'readonly',), $params);
            if (!empty($option['title'])) {
                $option_title = htmlentities(self::_wp($option['title'], $params), ENT_QUOTES, self::$default_charset);
                $control .= ">&nbsp;<label";
                $control .= self::addCustomParams(array('id' => 'for',), $params);
                $control .= self::addCustomParams(array('description' => 'title', 'class', 'style', 'disabled'), $option);
                $control .= ">{$option_title}</label>\n";
            } else {
                $control .= ">\n";
            }

            $control .= self::getControlDescription(array_merge($params, array('description' => null), $option));
            if ($id < count($options)) {
                $control .= $params['control_separator'];
            }
        }
        return $control;
    }

    private function getSelectControl($name, $params = array())
    {
        $control = '';
        $id = 0;
        $options = isset($params['options']) ? (is_array($params['options']) ? $params['options'] : array($params['options'])) : array();
        $control .= "<select name=\"{$name}\" autocomplete=\"off\"";
        $control .= self::addCustomParams(array('class', 'style', 'id', 'readonly', 'autofocus', 'disabled'), $params);
        $control .= ">\n";
        $group = null;
        foreach ($options as $option) {
            if ($group && (empty($option['group']) || (strcasecmp($option['group'], $group) != 0))) {
                $group = false;
                $control .= "\n</optgroup>\n";
            }
            if (!empty($option['group']) && ($option['group'] != $group)) {
                $group = (string)$option['group'];
                $custom_params = self::addCustomParams(
                    array(
                        'class'       => 'group_class',
                        'group_style' => 'style'
                    ),
                    $option
                );
                $control .= "\n<optgroup label=\"".htmlentities($group, ENT_QUOTES, self::$default_charset)."\"".$custom_params.">\n";
            }

            ++$id;
            $option_value = $option['value'];
            if (isset($params['value']) && ($option_value == $params['value'])) {
                $params['selected'] = 'selected';
            } elseif (isset($params['selected'])) {
                unset($params['selected']);
            }
            if (isset($option['description'])) {
                $params['description'] = $option['description'];
            }
            $option_value = htmlentities((string)$option_value, ENT_QUOTES, self::$default_charset);
            $control .= "<option value=\"{$option_value}\"";
            $control .= self::addCustomParams(array('selected'), $params);
            $control .= self::addCustomParams(array('class', 'style', 'disabled', 'description' => 'title',), $option);
            $option_title = htmlentities(self::_wp(ifset($option['title'], $option_value), $params), ENT_QUOTES, self::$default_charset);
            $control .= ">{$option_title}</option>\n";
        }
        if ($group) {
            $control .= "\n</optgroup>\n";
        }
        $control .= "</select>";
        return $control;
    }

    private function getGroupboxControl($name, $params = array())
    {
        $control = '';
        $options = isset($params['options']) ? (is_array($params['options']) ? $params['options'] : array($params['options'])) : array();
        if (!is_array($params['value'])) {
            $params['value'] = array();
        }
        self::addNamespace($params, $name);
        $wrappers = ifempty($params['options_wrapper'], array()) + array(
                'title_wrapper'       => '&nbsp;%s',
                'description_wrapper' => '<span class="hint">%s</span>',
                'control_wrapper'     => '%2$s'."\n".'%1$s'."\n".'%3$s'."\n",
                'control_separator'   => "<br>",

            );
        unset($params['options_wrapper']);
        $params = array_merge($params, $wrappers);
        $checkbox_params = $params;
        if (isset($params['options'])) {
            unset($checkbox_params['options']);
        }
        $id = 0;
        foreach ($options as $option) {
            $checkbox_params['value'] = !empty($option['value']) ? $option['value'] : 1;
            $checkbox_params['checked'] = in_array($option['value'], $params['value'], true) || !empty($params['value'][$option['value']]);
            $checkbox_params['title'] = empty($option['title']) ? null : $option['title'];
            $checkbox_params['description'] = ifempty($option['description']);
            $checkbox_params['disabled'] = ifempty($option['disabled']);
            if ($checkbox_params['disabled'] && !empty($option['checked'])) {
                $checkbox_params['checked'] = true;
            }
            $control .= self::getControl(self::CHECKBOX, $option['value'], $checkbox_params);
            if (++$id < count($options)) {
                $control .= $params['control_separator'];
            }
        }
        return $control;
    }

    private function getIntervalControl($name, $params = array())
    {
        $control = '';
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }
        $default_params = array(
            'value' => array(
                'from' => '',
                'to'   => '',
            )
        );
        $params['value'] = array_merge($default_params['value'], $params['value']);
        $input_params = $params;
        self::addNamespace($input_params, $name);
        $input_name = "from";
        $input_params['value'] = $params['value']['from'];
        $input_params['title'] = 'str_from';

        $control .= self::getControl(self::INPUT, $input_name, $input_params)."\n";

        $input_params = $params;
        $input_name = "to";
        self::addNamespace($input_params, $name);
        $input_params['value'] = $params['value']['to'];
        $input_params['title'] = 'str_to';
        $control .= self::getControl(self::INPUT, $input_name, $input_params)."\n";
        return $control;
    }

    private function getCheckboxControl($name, $params = array())
    {
        $control = '';
        $value = isset($params['value']) ? $params['value'] : false;
        if ($value) {
            if (!isset($params['checked'])) {
                $params['checked'] = 'checked';
            }
        } elseif (isset($params['checked'])) {
            unset($params['checked']);
        }
        if (empty($params['value'])) {
            $params['value'] = 1;
        }
        if (isset($params['label']) && $params['label']) {
            $control .= "<label";
            $control .= self::addCustomParams(array('for' => 'id'), $params);
            $control .= ">";
        }
        $control .= "<input type=\"checkbox\" name=\"{$name}\"";
        $control .= self::addCustomParams(array('value', 'class', 'style', 'checked', 'id', 'title', 'disabled',), $params);
        $control .= ">";
        if (isset($params['label']) && $params['label']) {
            $control .= '&nbsp;'.htmlentities(self::_wp($params['label'], $params), ENT_QUOTES, self::$default_charset)."</label>";
        }

        return $control;
    }

    private function getContactControl($name, $params = array())
    {
        $control = array();
        if ($name) {
            self::addNamespace($params, $name);
        }
        $params['namespace'] = $namespace = self::makeNamespace($params);
        $contact = wa()->getUser();
        $values = isset($params['value']) ? (array)$params['value'] : array();
        $custom_params = array('class', 'style', 'placeholder', 'id', 'readonly', 'disabled');
        $id = 0;
        foreach ((array)$params['options'] as $field) {
            $params['namespace'] = $namespace;
            $control[$id] = array(
                'title'       => '',
                'control'     => '',
                'description' => '',
            );
            $field_id = is_array($field) ? $field['value'] : $field;
            if (!isset($values[$field_id])) {
                $values[$field_id] = $contact->get($field_id);
            }

            $params['value'] = $values[$field_id];
            if (strpos($field_id, ':')) {
                list($field_id, $sub_field_id) = explode(':', $field_id, 2);
            } else {
                $sub_field_id = null;
            }
            if ($contact_field = waContactFields::get($field_id)) {
                if (is_array($params['value'])) {
                    $params['value'] = current($params['value']);
                }

                self::makeId($params, $field_id);
                $params['title'] = $contact_field->getName();
                $attrs = $this->addCustomParams($custom_params, $params);
                if ($sub_field_id) {
                    self::makeId($params, $sub_field_id);
                    $params['namespace'] = self::makeNamespace($params + array('name'));
                }

                unset($params['id']);
                if ($sub_field_id) {
                    if ($contact_sub_field = $contact_field->getFields($sub_field_id)) {
                        /**
                         * @var waContactField $contact_sub_field
                         */
                        $control[$id]['title'] .= ' '.$contact_sub_field->getName();
                        $params['id'] = "{$field_id}:{$sub_field_id}";
                        $control[$id]['control'] = $contact_sub_field->getHTML($params, $attrs);
                    } else {
                        $params['title'] .= ':'.$sub_field_id;
                        $control[$id]['title'] = $this->getControlTitle($params);
                        $control[$id]['control'] .= sprintf('<span class="error">%s<span>', _w('Contact field not found'));
                    }
                } else {
                    $control[$id]['control'] = $contact_field->getHTML($params, $attrs);

                    $control[$id]['title'] = $this->getControlTitle($params);
                }
            } else {

                $params['title'] = $field_id;
                $control[$id]['title'] = $this->getControlTitle($params);
                $control[$id]['control'] .= sprintf('<span class="error">%s<span>', _w('Contact field not found'));
            }
            ++$id;
        }
        return $control;
    }

    private function getContactfieldControl($name, $params = array())
    {
        $params['options'] = array();

        $params['options'][] = array(
            'title' => '—',
            'value' => '',
        );
        $fields = waContactFields::getAll();
        foreach ($fields as $field) {
            if ($field instanceof waContactCompositeField) {
                /**
                 * @var waContactCompositeField $field
                 */
                $sub_fields = $field->getFields();
                foreach ($sub_fields as $sub_field) {
                    /**
                     * @var waContactField $sub_field
                     */
                    $params['options'][] = array(
                        'group' => $field->getName(),
                        'title' => $sub_field->getName(),
                        'value' => $field->getId().'.'.$sub_field->getId(),
                    );
                }
            } else {
                /**
                 * @var waContactField $field
                 */
                $params['options'][] = array(
                    'title' => $field->getName(),
                    'value' => $field->getId(),
                );
            }
        }
        return $this->getSelectControl($name, $params);

    }

    /**
     * @todo complete params check
     * @param $name
     * @param array $params
     * @throws Exception
     * @return string
     */
    private function getCustomControl($name, $params = array())
    {
        /**
         * @var $callback callback|string
         */
        $callback = isset($params['callback']) ? $params['callback'] : null;
        if ($callback) {
            unset($params['callback']);
            if (is_array($callback)) {
                if (is_object($callback[0])) {
                    if (!method_exists($callback[0], $callback[1])) {
                        throw new Exception("Method {$callback[1]} not exists at class ".get_class($callback[0]));
                    }
                } elseif (!class_exists($callback[0])) {
                    throw new Exception("Class {$callback[0]} not found");
                }
                //TODO check method exists
            } else {
                if (!function_exists($callback)) {
                    throw new Exception("Function {$callback} not found");
                }
            }
            return call_user_func_array($callback, array($name, $params));
        }
        return null;
    }

    /**
     *
     * @param array $list
     * @param array $params
     * @return string
     */
    private function addCustomParams($list, $params = array())
    {
        $params_string = '';
        foreach ($list as $param => $target) {
            if (is_int($param)) {
                $param = $target;
            }
            if (isset($params[$param])) {
                $param_value = $params[$param];
                if (is_array($param_value)) {
                    if (array_filter($param_value, 'is_array')) {
                        $param_value = json_encode($param_value);
                    } else {
                        $param_value = implode(' ', $param_value);
                    }
                }
                if ($param_value !== false) {
                    if (in_array($param, array('title', 'description'))) {
                        $param_value = self::_wp($param_value, $params);
                    } elseif (in_array($param, array('disabled', 'readonly'))) {
                        $param_value = $param;
                    }
                    $param_value = htmlentities((string)$param_value, ENT_QUOTES, self::$default_charset);
                    if (in_array($param, array('autofocus'))) {
                        $params_string .= " {$target}";
                    } else {
                        $params_string .= " {$target}=\"{$param_value}\"";
                    }
                }
            }
        }
        return $params_string;
    }

    private static function _wp($param, $params = array())
    {
        $translate = (!empty($params['translate']) && is_callable($params['translate'])) ? $params['translate'] : '_wp';
        if (is_array($param)) {
            if (!isset($params['translate']) || !empty($params['translate'])) {
                $param[key($param)] = call_user_func($translate, current($param));
            }
            $string = call_user_func_array('sprintf', $param);
        } elseif (!isset($params['translate']) || !empty($params['translate'])) {
            $string = call_user_func($translate, $param);
        } else {
            $string = $param;
        }
        return $string;
    }
}
