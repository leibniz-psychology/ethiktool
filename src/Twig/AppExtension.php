<?php

namespace App\Twig;

use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array {
        return [new TwigFunction('boolToDisplay',[$this,'boolToDisplay']),
                new TwigFunction('boolToString',[$this,'boolToString']),
                new TwigFunction('addDisableTarget',[$this,'addDisableTarget']),
                new TwigFunction('addTargetArray',[$this,'addTargetArray']),
                new TwigFunction('addTarget',[$this,'addTarget']),
                new TwigFunction('addLabelClass',[$this,'addLabelClass']),
                new TwigFunction('addClass',[$this,'addClass']),
                new TwigFunction('getAnySelected',[$this,'getAnySelected'])];
    }

    /** Returns the display value for a tag.
     * @param bool $bool bool that gets converted
     * @param int $display if $bool is true: 1: display is set to grid, 2: display is set to flex, otherwise to block
     * @return string 'block', 'grid', or 'flex' if $bool is true, 'none' otherwise
     */
    public function boolToDisplay(bool $bool, int $display = 0): string {
        return 'display: '.($bool ? ($display===1 ? 'grid' : ($display===2 ? 'flex' : 'block')) : 'none');
    }

    /** Converts a boolean to a string.
     * @param bool $bool bool that gets converted
     * @return string string representation of the bool
     */
    public function boolToString(bool $bool): string {
        return $bool ? 'true' : 'false';
    }

    /** Creates an array to be passed as the second argument of a form_widget() call. The array contains a stimulus target 'disableLoad' which is passed to the base controller.
     * @param bool $addAttribute if true, the array will be wrapped inside an 'attr' array
     * @return array array for the form_widget() call
     */
    public function addDisableTarget(bool $addAttribute = false): array {
        return  $addAttribute ? $this->addTargetArray('base','disableLoad') : $this->addTarget('base','disableLoad');
    }

    /** Creates an array to be passed as the second argument of a form_widget() call. The array contains a stimulus target.
     * @param string $controller controller where the target gets passed to
     * @param string $target name of the target
     * @return array array for the form_widget() call
     */
    public function addTargetArray(string $controller, string $target): array {
        return ['attr' => $this->addTarget($controller,$target)];
    }

    /** Creates an array to be passed as the 'attr' value of the second argument of a form_widget() call. The array contains a stimulus target.
     * @param string $controller controller where the target gets passed to
     * @param string $target name of the target
     * @return array array for the form_widget() call
     */
    public function addTarget(string $controller, string $target): array {
        return ['data-'.$controller.'-target' => $target];
    }

    /** Creates an array to be passed as the third argument of a form_label() call.
     * @param string $classname classnames
     * @param string $style if provided, a second key 'style' is added
     * @return array array for the form_label() cal
     */
    public function addLabelClass(string $classname, string $style = ''): array {
        return ['label_attr' => $this->addClass($classname,$style)];
    }

    /** Creates an array to be passed to the 'attr' value of the second argument of a form_widget() or the third argument of a form_label() call. The array contains one or more classes and eventually a 'style' key.
     * @param string $classname classnames
     * @param string $style if provided, a second key 'style' is added
     * @return array array for the form_widget() call
     */
    public function addClass(string $classname, string $style = ''): array {
        return array_merge(['class' => $classname], $style!=='' ? ['style' => $style] : []);
    }

    /** Checks if either the checkbox with the name 'unique' or any of the other checkboxes in keys is selected.
     * @param FormView $forms form array
     * @param array $keys keys to be checked
     * @param string $unique key whose selection means that no other key in $keys can be selected
     * @return array 0: true if 'unique' key is selected, 1: true if any of the other keys is selected, otherwise false in both cases, 2: number of selected checkboxes excluding the $unique one
     */
    public function getAnySelected(FormView $forms, array $keys, string $unique = ''): array {
        $anySelected = false;
        $uniqueSelected = false;
        $numSelected = 0;
        foreach ($keys as $key) {
            $isChecked = $forms[$key]->vars['checked'];
            if ($key!==$unique) {
                $anySelected = $anySelected || $isChecked;
                $numSelected += $isChecked ? 1 : 0;
            }
            else {
                $uniqueSelected = $isChecked;
            }
        }
        return [$uniqueSelected,$anySelected,$numSelected];
    }
}