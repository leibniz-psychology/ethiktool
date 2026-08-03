<?php

namespace App\Twig;

use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;

class AppExtension
{
    /** Returns the display value for a tag.
     * @param bool $bool bool that gets converted
     * @param int $display if $bool is true: 1: display is set to grid, 2: display is set to flex, otherwise to block
     * @return string 'block', 'grid', or 'flex' if $bool is true, 'none' otherwise
     */
    #[AsTwigFunction(name: 'boolToDisplay')]
    public function boolToDisplay(bool $bool, int $display = 0): string
    {
        return 'display: '.($bool ? ($display===1 ? 'grid' : ($display===2 ? 'flex' : 'block')) : 'none');
    }

    /** Converts a boolean to a string.
     * @param bool $bool bool that gets converted
     * @return string string representation of the bool
     */
    #[AsTwigFunction(name: 'boolToString')]
    public function boolToString(bool $bool): string
    {
        return $bool ? 'true' : 'false';
    }

    /** Creates an array to be passed as the second argument of a form_widget() call. The array contains a stimulus target 'disableLoad' which is passed to the base controller.
     * @param bool $addAttribute if true, the array will be wrapped inside an 'attr' array
     * @return array array for the form_widget() call
     */
    #[AsTwigFunction(name: 'addDisableTarget')]
    public function addDisableTarget(bool $addAttribute = false): array
    {
        return  $addAttribute ? $this->addTargetArray('base','disableLoad') : $this->addTarget('base','disableLoad');
    }

    /** Creates an array to be passed as the second argument of a form_widget() call. The array contains a stimulus target.
     * @param string $controller controller where the target gets passed to
     * @param string $target name of the target
     * @return array<string, mixed[]> array for the form_widget() call
     */
    #[AsTwigFunction(name: 'addTargetArray')]
    public function addTargetArray(string $controller, string $target): array
    {
        return ['attr' => $this->addTarget($controller,$target)];
    }

    /** Creates an array to be passed as the 'attr' value of the second argument of a form_widget() call. The array contains a stimulus target.
     * @param string $controller controller where the target gets passed to
     * @param string $target name of the target
     * @return array array for the form_widget() call
     */
    #[AsTwigFunction(name: 'addTarget')]
    public function addTarget(string $controller, string $target): array
    {
        return ['data-'.$controller.'-target' => $target];
    }

    /** Creates an array to be passed as the third argument of a form_label() call.
     * @param array $attributes attributes to be added
     * @return array<string, mixed[]> array for the form_label() cal
     */
    #[AsTwigFunction(name: 'addLabelAttributes')]
    public function addLabelAttributes(array $attributes): array
    {
        return ['label_attr' => $attributes];
    }

    /** Creates an array to be passed to the 'attr' value of the second argument of a form_widget() or the third argument of a form_label() call. The array contains one or more classes and eventually a 'style' key.
     * @param string $classname classnames
     * @param bool $addAttr if true, the array will be wrapped in an 'attr' key
     * @param string $style if provided, a second key 'style' is added
     * @return array array for the form_widget() call
     */
    #[AsTwigFunction(name: 'addClass')]
    public function addClass(string $classname, bool $addAttr = false, string $style = ''): array
    {
        $returnArray = array_merge($classname!=='' ? ['class' => $classname] : [], $style!=='' ? $this->addStyle($style) : []);
        return $addAttr ? ['attr' => $returnArray] : $returnArray;
    }

    /** Creates an array to be passed to the 'attr' value of the second argument of a form_widget(), the third argument of a form_label() call, or the 'arguments' array of a addTextfield() call. The array contains a 'style' key which is eventually wrapped in an 'attributes' key.
     * @param string $style style
     * @param bool $addAttributes if true, the style array is wrapped in an 'attributes' key
     * @return string[] array for the call
     */
    #[AsTwigFunction(name: 'addStyle')]
    public function addStyle(string $style, bool $addAttributes = false): array
    {
        $styleArray = ['style' => $style];
        return $addAttributes ? ['attributes' => $styleArray] : $styleArray;
    }

    /** Checks if either any checkbox with the name 'unique' or any of the other checkboxes in keys is selected.
     * @param FormView $forms form array
     * @param array $keys keys to be checked
     * @param string|array $unique keys whose selections means that no other key in $keys can be selected
     * @return array<int, bool|int> 0: true if any 'unique' key is selected, 1: true if any of the other keys is selected, otherwise false in both cases, 2: number of selected checkboxes excluding the $unique one
     */
    #[AsTwigFunction(name: 'getAnySelected')]
    public function getAnySelected(FormView $forms, array $keys, string|array $unique = ''): array
    {
        $anySelected = false;
        $uniqueSelected = false;
        $numSelected = 0;
        if (!is_array($unique)) {
            $unique = [$unique];
        }
        foreach ($keys as $key) {
            $isChecked = $forms[$key]->vars['checked'];
            if (!in_array($key,$unique)) {
                $anySelected = $anySelected || $isChecked;
                $numSelected += $isChecked ? 1 : 0;
            } else {
                $uniqueSelected = $uniqueSelected || $isChecked;
            }
        }
        return [$uniqueSelected,$anySelected,$numSelected];
    }

    /** Checks if an element is an array.
     * @param array|string $element element to be checked
     * @return bool true if element is an array, false otherwise
     */
    #[AsTwigFunction(name: 'isArray')]
    public function isArray(array|string $element): bool
    {
        return is_array($element);
    }
}
