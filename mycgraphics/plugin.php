<?php
/*
  Plugin Name: MYCGraphics Customizations
  Plugin URI: https://www.iaps.ca/
  Description: Provides MYCdb Customer ID field.
  Author: Brett Farrell
  Version: 1.1.0
  Author URI: https://www.iaps.ca/
 */

class mycgraphics_customizations {

    function __construct() {
        add_action('init', array($this, 'init'));
    }

    function init() {
        if (!is_admin())
            return;
        add_action('show_user_profile', array($this, 'user_profile_fields'));
        add_action('edit_user_profile', array($this, 'user_profile_fields'));
        add_action('personal_options_update', array($this, 'user_profile_fields_save'));
        add_action('edit_user_profile_update', array($this, 'user_profile_fields_save'));
    }

    function user_profile_fields($user) {
        $controller = new mycgraphics_customizations_profile_controller();
        $controller->fields($user);
    }

    function user_profile_fields_save($user_id) {
        $controller = new mycgraphics_customizations_profile_controller();
        $controller->update($user_id);
    }

}

$mycgraphics_customizations = new mycgraphics_customizations();

/**
 * This class is used to display and update user profile fields, 
 * the values of which are used within $pvo_plugin->view().
 */
class mycgraphics_customizations_profile_controller {

    const META_PREFIX = 'mycdb_';

    function fields_list() {
        return [
            self::META_PREFIX . 'company_id' => mycgraphics_customizations_custom_field::i()->set('label', 'MYCdb Company ID'),
            self::META_PREFIX . 'company_contact_id' => mycgraphics_customizations_custom_field::i()->set('label', 'MYCdb Company Contact ID'),
        ];
    }

    function fields(WP_User $user) {
        ?>
        <h2>MYCdb Customer Information</h2>
        <table class="table form-table">
            <tbody><?php
                foreach ($this->fields_list() as $name => $config):
                    if ($config->display):
                        ?><tr>
                            <th class="col-sm-3"><label for="<?= $name ?>"><?= $config->label ?></label></th>
                            <td class="col-sm-9"><?php
                                switch ($config->type):
                                    case 'text':
                                    case 'date':
                                        echo '<input type="text" name="' . $name . '" id="' . $name . '" 
                                               value="' . esc_attr(get_user_meta($user->ID, $name, true)) . '" 
                                               class="form-control text-right regular-text" ' . (!is_admin() ? 'readonly' : '') . ' />';
                                        break;
                                    case 'textarea':
                                        echo '<textarea name="' . $name . '" id="' . $name . '" 
                                               class="form-control text-right regular-text" ' . (!is_admin() ? 'readonly' : '') . '>' . esc_textarea(get_user_meta($user->ID, $name, true)) . '</textarea>';
                                        break;
                                endswitch;
                                echo trim($config->description) ? '<p>' . $config->description . '</p>' : null;
                                ?></td>
                        </tr><?php
                    endif;
                endforeach;
                ?></tbody>
        </table>
        <?php
    }

    function update($user_id) {
        if (!is_admin())
            return;

        foreach ($this->fields_list() as $name => $config):
            if (array_key_exists($name, $_POST) && false != ($filtered_value = sanitize_text_field($_POST[$name])))
                update_user_meta($user_id, $name, $filtered_value);
        endforeach;
    }

}

class mycgraphics_customizations_custom_field extends mycgraphics_customizations_base_model {

    public $label;
    public $type = 'text';
    public $description;
    public $display = true;

}

class mycgraphics_customizations_base_model {

    /**
     * 
     * @return \static
     */
    static function i() {
        return new static;
    }

    /**
     * 
     * @param string $property
     * @param mixed $value
     * @return \mycgraphics_customizations_base_model
     */
    function set($property, $value) {
        if (property_exists($this, $property))
            $this->$property = $value;
        return $this;
    }

}
