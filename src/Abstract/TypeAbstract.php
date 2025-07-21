<?php

namespace App\Abstract;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Contains all methods that are used by several forms (*Type class). Therefore, it extends AbstractType. All form classes inherit this class. */
abstract class TypeAbstract extends AbstractType implements DataMapperInterface
{
    public static TranslatorInterface $translator;
    protected const labelParams = 'label_translation_parameters'; // key in the $options array for translation parameters of the label
    protected const attrParams = 'attr_translation_parameters'; // key in tne $options array for translation parameters of attributes
    // variables for translation keys of hints
    protected const textHint = 'textHint'; // above text fields
    protected const textHintPlural = 'textHints'; // above text fields

    public function __construct(TranslatorInterface $translator) {
        self::$translator = $translator;
    }

    // functions

    /** Checks for each value in \$keys if the key in \$forms exists and if so, adds a key to an array with the value being either empty or the value of another form field.
     * @param array $forms Form element holding the data
     * @param array $keys keys to be checked in $forms
     * @param array $otherIDs if not empty, checks for checkboxes whose selection make a text field visible. Keys: IDs of checkboxes, values: keys of the form fields for the values to be added
     * @param string $exclusive if provided and the respective key in $forms is selected, no further keys are checked
     * @return array keys: all values in \$keys which exist in \$forms, values: empty strings except the key \$otherID, if selected
     */
    protected function getSelectedCheckboxes(array $forms, array $keys, array $otherIDs = [], string $exclusive = ''): array {
        $returnArray = [];
        foreach ($keys as $key) {
            if ($this->getFormData($forms,$key,false)) { // checkbox was selected
                $returnArray[$key] = !array_key_exists($key,$otherIDs) ? '' : $forms[$otherIDs[$key]]->getData(); // if other group is selected, get description
                if ($key===$exclusive) { // if $exclusive is checked immediately before/after any of the other checkboxes is checked (i.e., the second of these two is checked before the page was reloaded after submission), keep only the $exclusive key
                    break;
                }
            }
        }
        return $returnArray;
    }

    /** Creates an array with the key 'chosen' and the value from \$forms[\$chosenKey]. If the value equals \$selection, then for each element in \$furtherElements, another element is added to the array.
     * @param array $forms Form element holding the data
     * @param string $chosenKey key(s) to be checked in $forms
     * @param int|string $selection value where further elements are added to the array
     * @param array $furtherElements further elements that are added to the array. Keys: either numerical or keys to be used for the array. Values: keys in $forms
     * @param bool $useKeys if true, then the keys in \$furtherElements are used as keys for the further elements in the array, otherwise the values in $furtherElements are used as keys
     * @return array array with a 'chosen' keys and eventually further keys
     */
    protected function getChosenArray(array $forms, string $chosenKey, int|string $selection, array $furtherElements, bool $useKeys = false): array {
        $chosen = $forms[$chosenKey]->getData();
        $returnArray = ['chosen' => $chosen];
        if ($chosen===$selection) {
            foreach ($furtherElements as $key => $formKey) {
                $returnArray[$useKeys ? $key : $formKey] = $forms[$formKey]->getData();
            }
        }
        return $returnArray;
    }

    /** Checks if a key exists in an array. If so, it returns the value, otherwise an empty string.
     * @param $array array array where the key is searched
     * @param $key string key to be searched
     * @return string the value of the key if it exists, otherwise an empty string
     */
    protected function getArrayValue(array $array, string $key): string {
        return $array[$key] ?? '';
    }

    /** Checks if \$key exists in $forms and if so, gets the data from it.
     * @param array $forms array containing the form data
     * @param string $key key to be checked
     * @param mixed $default value that is returned if $key does not exist
     * @return mixed data from \$key or an empty string if \$key does not exist
     */
    protected function getFormData(array $forms, string $key, mixed $default = ''): mixed {
        return array_key_exists($key,$forms) ? $forms[$key]->getData() : $default;
    }

    /** Creates an array with keys 'min' and 'max'.
     * @param int|float $min value for the 'min' key
     * @param int $max value for the 'max' key
     * @return array array with two keys and values
     */
    protected function setMinMax(int|float $min, int $max): array {
        return ['min' => $min, 'max' => $max];
    }

    // methods

    /** Creates a checkbox and a text field.
     * @param FormBuilderInterface $builder FormBuilder where the element is created
     * @param string $name name of the widget
     * @param string $label label which is displayed next to the checkbox
     * @param string $textHint text that is displayed above the text field if nothing was entered
     * @return void
     */
    protected function addCheckboxTextfield(FormBuilderInterface $builder, string $name, string $label, string $textHint = ''): void {
        $this->addFormElement($builder,$name,'checkbox',$label);
        $this->addFormElement($builder,$name.'Text','textarea',hint: $textHint);
    }

    /** Creates two radio buttons next to each other with 'yes' and 'no' as labels.
     * @param FormBuilderInterface $builder FormBuilder where the element is created
     * @param string $name name of the widget
     * @param string|bool $label label next to the group of false if no label should be added
     * @param string $textareaName if not an empty string, name of a textarea
     * @param string $textHint if $textareaName is not an empty string, the text that is displayed above the textarea
     * @param array $options additional options that are passed to the FormBuilder
     * @return void
     */
    protected function addBinaryRadio(FormBuilderInterface $builder, string $name, string|bool $label = false, string $textareaName = '', string $textHint = '', array $options = []): void {
        $this->addRadioGroup($builder,$name,['buttons.yes' => 0, 'buttons.no' => 1],$label,$textareaName,$textHint,$options);
    }

    /** Creates a group of radio buttons and, if $textareaName is provided, a textarea.
     * @param FormBuilderInterface $builder Formbuilder where the elements are created
     * @param string $name name of the widget
     * @param array $choices keys and value of the radio buttons
     * @param string|bool $label label next to the group or false if no label should be added
     * @param string $textareaName if not an empty string, name of a textarea
     * @param string $textHint if $textareaName is not an empty string, the text that is displayed above the textarea
     * @param array $options additional options that are passed to the FormBuilder
     * @return void
     */
    protected function addRadioGroup(FormBuilderInterface $builder, string $name, array $choices, string|bool $label = false, string $textareaName = '', string $textHint = '', array $options = []): void {
        $this->addFormElement($builder,$name,'choice',$label,array_merge(['choices' => $choices, 'expanded' => true],$options));
        if ($textareaName!=='') {
            $this->addFormElement($builder,$textareaName,'textarea',hint: $textHint);
        }
    }

    /** Creates a group of checkboxes.
     * @param FormBuilderInterface $builder FormBuilder where the elements are created
     * @param array $names names of the widgets. Must equal the last part of the translation key unless $labelNames is provided
     * @param string $translationKey prefix of the translation keys for the label
     * @param string|array $otherNames text fields that are added with these names
     * @param string|array $textHints if \$otherNames is not empty, then the text that is used as a placeholder for the text fields. Must be the same size as $otherNames
     * @param string $textareaName if not an empty string, name of a textarea
     * @param string $textareaTextHint if $textareaName is not an empty string, the text that is displayed above the textarea
     * @param array $labelNames if provided, last part of the translation key
     * @param array $options additional options for the checkboxes
     * @return void
     */
    protected function addCheckboxGroup(FormBuilderInterface $builder, array $names, string $translationKey, string|array $otherNames = [], string|array $textHints = [], string $textareaName = '', string $textareaTextHint = '', array $labelNames = [], array $options = []): void {
        if ($labelNames===[]) {
            $labelNames = $names;
        }
        foreach ($names as $index => $name) {
            $this->addFormElement($builder,$name,'checkbox',$translationKey.($labelNames[$index]),$options);
        }
        if (!is_array($otherNames)) {
            $otherNames = [$otherNames];
        }
        if (!is_array($textHints)) {
            $textHints = [$textHints];
        }
        foreach ($otherNames as $index => $name) {
            $this->addFormElement($builder,$name,'text',hint: $textHints[$index]);
        }
        if ($textareaName!=='') {
            $this->addFormElement($builder,$textareaName,'textarea',hint: $textareaTextHint);
        }
    }

    /** Adds the submit dummy textarea and the load form.
     * @param FormBuilderInterface $builder FormBuilder where the element is created
     * @return void
     */
    protected function addDummyForms(FormBuilderInterface $builder): void {
        $this->addFormElement($builder,ControllerAbstract::submitDummy,'textarea');
        $builder->add(ControllerAbstract::loadInput,FileType::class,['empty_data' => '', 'required' => false, 'attr' => ['type' => 'file', 'accept' => '.xml']]);
    }

    /** Creates an element in a form.
     * @param FormBuilderInterface $builder FormBuilder where the element is created. 'empty_data' will be set to an empty string and 'required' will be set to false.
     * @param string $name internal name of the element
     * @param string $class Object of the element
     * @param string|bool $label label of the element
     * @param array $options additional options for the element depending on the type
     * @param string $hint hint that is placed above a text field (will be passed as the placeholder to the template) or the placeholder a radio button group
     * @return void
     */
    protected function addFormElement(FormBuilderInterface $builder, string $name, string $class, string|bool $label = false, array $options = [], string $hint = ''): void {

        $classType = null;
        $addOptions = array_merge(['label' => $label, 'required' => false, self::labelParams => $options[self::labelParams] ?? [], self::attrParams => $options[self::attrParams] ?? []], ['attr' => ['placeholder' => str_contains($class,'text') ? $hint : false, 'autocomplete' => 'off']]);
        switch ($class) {
            case 'choice':
                $builder->add($name, ChoiceType::class, array_merge(['choices' => $options['choices'], 'empty_data' => '', 'expanded' => $options['expanded'] ?? false, 'multiple' => $options['multiple'] ?? false, 'placeholder' => $hint ?: false],$addOptions));
                break;
            case 'date':
                $builder->add($name, DateType::class, array_merge(['empty_data' => '','widget' => 'single_text', 'model_timezone' => 'Europe/Berlin'],$addOptions));
                break;
            case 'checkbox':
                $builder->add($name,CheckboxType::class, $addOptions);
                break;
            case 'spinner':
                $builder->add($name,NumberType::class,array_merge(['html5' => true],array_merge($addOptions,['attr' => ['style' => 'min-width: auto', 'min' => $options['min'], 'max' => $options['max']]])));
                break;
            case 'submit':
                $builder->add($name,SubmitType::class,['label' => $label, self::labelParams => $options[self::labelParams] ?? []]); // no additional options for submit buttons
                break;
            case 'text':
                $classType = TextType::class;
                break;
            case 'textarea':
                $classType = TextareaType::class;
                break;
            case 'money': // currently only used once in compensation
                $classType = MoneyType::class;
        }
        if ($classType) {
            $builder->add($name, $classType, array_merge(['empty_data' => ''],$addOptions,$options));
        }
    }

    /** Sets for each value in \$keys the corresponding key in \$forms to true or to a string.
     * @param array $forms Form element where the data is set
     * @param array|string $data keys: keys to be set to true in \$forms, values: if the key equals \$otherId, the value for the \$otherDescription element, empty otherwise
     * @param array $otherIDs if not empty, sets other form elements. Keys: keys in $data, values: id of the form element to be set
     */
    protected function setSelectedCheckboxes(array $forms, array|string $data, array $otherIDs = []): void {
        if ($data!=='') {
            foreach ($data as $key => $value) {
                $forms[$key]->setData(true);
                if (array_key_exists($key,$otherIDs)) {
                    $forms[$otherIDs[$key]]->setData($value);
                }
            }
        }
    }

    /** Sets the values of spinner if \$keys exist in \$data and the values in \$data are not an empty string. If \$dataKeys is not provided, the keys in \$forms and \$data must be the same.
     * @param array $forms Form element where the data is set
     * @param array $data array containing the values to be set
     * @param array|string $keys keys in \$forms and $data to be set. Can either be a single key or an array of keys
     * @param array|string $dataKeys if provided, the corresponding keys in $data
     * @param string $exclude if provided, value where the elements in $forms are not set
     * @return void
     */
    protected function setSpinner(array $forms, array $data, array|string $keys, array|string $dataKeys = [], string $exclude = ''): void {
        $keys = is_string($keys) ? [$keys] : $keys;
        if ($dataKeys===[]) {
            $dataKeys = $keys;
        }
        foreach (array_combine($keys,is_string($dataKeys) ? [$dataKeys] : $dataKeys) as $key => $value) {
            if (array_key_exists($value,$data)) {
                $tempVal = $data[$value];
                if ($tempVal!=='' && $tempVal!==$exclude) {
                    $forms[$key]->setData($tempVal);
                }
            }
        }
    }

    /** Sets a form element by getting the value from the 'chosen' key of \$array. For each element in \$furtherElements, further form elements are set.
     * @param array $forms Form element holding the widgets
     * @param array $array array containing the 'chosenKey' key which has a child 'chosen' and eventually further children. Keys: either numerical or keys to be used for the array. Values: keys in $forms
     * @param string $chosenKey key of the form element to be set with the 'chosen' value
     * @param array $furtherElements further form elements to be set. For each element, it is first checked if the key exists in $array
     * @param bool $useKeys if true, the keys from \$furtherElements are used as the keys for \$array, otherwise the values in $furtherElements are used as keys
     * @return void
     */
    protected function setChosenArray(array $forms, array $array, string $chosenKey, array $furtherElements, bool $useKeys = false): void {
        $tempArray = $array[$chosenKey];
        $forms[$chosenKey]->setData($tempArray['chosen']);
        foreach ($furtherElements as $key => $formKey) {
            $forms[$formKey]->setData($this->getArrayValue($tempArray,$useKeys ? $key :$formKey));
        }
    }
}