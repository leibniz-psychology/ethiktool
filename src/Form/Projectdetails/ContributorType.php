<?php

namespace App\Form\Projectdetails;

use App\Abstract\ControllerAbstract;
use App\Abstract\TypeAbstract;
use App\Traits\Contributors\ContributorsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class ContributorType extends TypeAbstract
{
    use ContributorsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        foreach ($options[self::dummyParams][self::taskNode] as $task) {
            foreach ($task as $key => $value) {
                $this->addFormElement($builder,$key,'checkbox',$value);
            }
        }
        $builder->setDataMapper($this);
        $this->addDummyForms($builder);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        foreach (self::tasksNodes as $tasksNode) {
            $tasks = $viewData[$tasksNode];
            if ($tasks!=='') {
                foreach (explode(',',$tasks) as $contributor) {
                    $forms[$tasksNode.$contributor]->setData(true);
                }
            }
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $viewData = array_combine(self::tasksNodes,array_fill(0,count(self::tasksNodes),''));
        foreach ($forms as $key => $element) {
            if ($key!==self::language && $key!==ControllerAbstract::submitDummy && $element->getData()) {
                $viewData[$this->splitStringByInt($key)] .= ','.$this->splitStringByInt($key,false);
            }

        }
        foreach (self::tasksNodes as $task) {
            $contributor = $viewData[$task];
            if ($contributor!=='') {
                $viewData[$task] = substr($contributor,1); // remove first comma
            }
        }
    }

    /** Finds the first digit in a string and splits it at that position. There must only be digits after the first digit.
     * @param string $input string to be split
     * @param bool $returnString if true, then the substring from to start to first digit (exclusive) is returned, else the substring from first digit (inclusive) to end as an int
     * @return string|int either the part before the digit(s) or the digit(s), depending on $returnString
     */
    private function splitStringByInt(string $input, bool $returnString = true): string|int {
        $intPos = strcspn($input,'0123456789');
        return $returnString ? substr($input,0,$intPos) : (int)(substr($input,$intPos));
    }
}