<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * module wrapper
 */

abstract class ConfigurableModule010000 extends ExternalModule010000 implements ConfigurableModuleInterface010000
{
	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * @var
	 */
	protected $hasSettings = false;

	/**
	 * settings builder
	 *
	 * @return bool
	 */
	static public function outputModuleSettings($module, $formContainer)
	{
		if ($module->hasSettings) {
			return false;
		}

		$form = new Form('', '', '', false, '', true);

		foreach ($module->settings as $name => $setting) {
			$setting['name'] = $name;
			ConfigurableModule010000::buildSetting($setting, $form, $formContainer);
		}

		return true;
	}

	/**
	 * creates a single setting from an associative array
	 *
	 * @param  array
	 * @param  DefaultForm
	 * @param  DefaultFormContainer
	 * @return void
	 */
	static public function buildSetting($setting, $form, $formContainer)
	{
		$options = '';
		$type = explode("\n", $setting['optionscode']);
		$type = array_map('trim', $type);
		$elementName = "{$setting['name']}";
		$elementId = "setting_{$setting['name']}";

		$label = '<strong>' . htmlspecialchars_uni($setting['title']) . '</strong>';
		$description = '<i>' . $setting['description'] . '</i>';

		if ($type[0] == 'text' ||
			$type[0] == '') {
			$formContainer->output_row($label, $description, $form->generate_text_box($elementName, $setting['value'], array('id' => $elementId)), $elementName, array("id" => $elementId));
		} else if ($type[0] == 'textarea') {
			$formContainer->output_row($label, $description, $form->generate_text_area($elementName, $setting['value'], array('id' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'yesno') {
			$formContainer->output_row($label, $description, $form->generate_yes_no_radio($elementName, $setting['value'], true, array('id' => $elementId.'_yes', 'class' => $elementId), array('id' => $elementId.'_no', 'class' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'onoff') {
			$formContainer->output_row($label, $description, $form->generate_on_off_radio($elementName, $setting['value'], true, array('id' => $elementId.'_on', 'class' => $elementId), array('id' => $elementId.'_off', 'class' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'language') {
			$languages = $lang->get_languages();
			$formContainer->output_row($label, $description, $form->generate_select_box($elementName, $languages, $setting['value'], array('id' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'adminlanguage') {
			$languages = $lang->get_languages(1);
			$formContainer->output_row($label, $description, $form->generate_select_box($elementName, $languages, $setting['value'], array('id' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'passwordbox') {
			$formContainer->output_row($label, $description, $form->generate_password_box($elementName, $setting['value'], array('id' => $elementId)), $elementName, array('id' => $elementId));
		} else if ($type[0] == 'php') {
			$setting['optionscode'] = substr($setting['optionscode'], 3);
			eval("\$code = \"" . $setting['optionscode'] . "\";");
		} else {
			for ($i=0; $i < count($type); $i++) {
				$optionsexp = explode('=', $type[$i]);
				if (!$optionsexp[1]) {
					continue;
				}

				if ($type[0] == 'select') {
					$option_list[$optionsexp[0]] = htmlspecialchars_uni($optionsexp[1]);
				} else if ($type[0] == 'radio') {
					if ($setting['value'] == $optionsexp[0]) {
						$option_list[$i] = $form->generate_radio_button($elementName, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $elementId.'_'.$i, "checked" => 1, 'class' => $elementId));
					} else {
						$option_list[$i] = $form->generate_radio_button($elementName, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $elementId.'_'.$i, 'class' => $elementId));
					}
				} else if($type[0] == 'checkbox') {
					if ($setting['value'] == $optionsexp[0]) {
						$option_list[$i] = $form->generate_check_box($elementName, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $elementId.'_'.$i, "checked" => 1, 'class' => $elementId));
					} else {
						$option_list[$i] = $form->generate_check_box($elementName, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $elementId.'_'.$i, 'class' => $elementId));
					}
				}
			}
			if ($type[0] == 'select') {
				$formContainer->output_row($label, $description, $form->generate_select_box($elementName, $option_list, $setting['value'], array('id' => $elementId)), $elementName, array('id' => $elementId));
			} else {
				$code = implode('<br />', $option_list);
			}
		}

		if ($code) {
			$formContainer->output_row($label, $description, $code, '', array(), array('id' => 'row_' . $elementId));
		}
	}

	/**
	 * customize load
	 *
	 * @return string the return of the module routine
	 */
	public function load($module)
	{
		if (!parent::load($module)) {
			return false;
		}

		$this->hasSettings = !empty($this->settings);
		return true;
	}

	/**
	 * output settings
	 *
	 * @return string the return of the module routine
	 */
	public function outputSettings($formContainer)
	{
		ConfigurableModule010000::outputModuleSettings($this, $formContainer);
	}
}

?>
