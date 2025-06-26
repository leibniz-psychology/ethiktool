<?php

namespace App\Form\Main;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use App\Traits\Main\CompleteFormTrait;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class CompleteFormType extends TypeAbstract
{
    use CompleteFormTrait;
    use AppDataTrait; // for votes PDF

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'completeForm.';
        // consent
        $this->addCheckboxTextfield($builder,self::consent,$translationPrefix.self::consent.'.confirm');
        // bias
        $this->addCheckboxGroup($builder,self::biasTypes,$translationPrefix.'bias.types.');
        $this->addFormElement($builder,self::biasParticipate.self::descriptionCap,'textarea');
        $this->addFormElement($builder,self::biasAccess.self::descriptionCap,'textarea');
        // consent further
        $this->addFormElement($builder,self::consentFurther,'checkbox',$translationPrefix.self::consentFurther.'.consent');
        // PDFs
        $pdf = $options[self::dummyParams];
        if ($pdf!==[]) {
            $tempPrefix = $translationPrefix.'pdf.';
            if (array_key_exists(self::voteNode,$pdf)) {
                $this->addPDFfield($builder,self::voteNode,$tempPrefix.self::voteNode);
            }
            if (array_key_exists('projectdetails',$pdf)) {
                $tempPrefix .= 'projectdetails.';
                foreach ($pdf['projectdetails'] as $studyID => $study) {
                    foreach ($study as $groupID => $group) {
                        foreach ($group as $measureID => $measure) {
                            foreach ($measure as $key => $value) {
                                $this->addPDFfield($builder,$this->concatIDs([$studyID,$groupID,$measureID],$key),$tempPrefix.$key,$value);
                            }
                        }
                    }
                }
            }
        }
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // consent
        $forms[self::consent]->setData($viewData[self::consent]==='1');
        // further message to committee
        $forms[$this->appendText(self::consent)]->setData($viewData[self::descriptionNode]);
        // bias
        $tempArray = $viewData[self::bias];
        if ($tempArray!=='') {
            foreach ($tempArray as $bias => $value) {
                $forms[$bias]->setData(true);
                if ($bias!==self::noBias) {
                    $forms[$bias.self::descriptionCap]->setData($value);
                }
            }
        }
        // consent further
        $forms[self::consentFurther]->setData($viewData[self::consentFurther]==='1');
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // consent
        $viewData[self::consent] = $forms[self::consent]->getData();
        // further message to committee
        $viewData[self::descriptionNode] = $forms[$this->appendText(self::consent)]->getData();
        // bias
        $tempArray = [];
        foreach (self::biasTypes as $bias) {
            if ($forms[$bias]->getData()) {
                $tempArray[$bias] = $bias!==self::noBias ? $forms[$bias.self::descriptionCap]->getData() : '';
            }
        }
        $viewData[self::bias] = $tempArray;
        // consent further
        $viewData[self::consentFurther] = $forms[self::consentFurther]->getData();
    }

    /** Adds a pdf element.
     * @param FormBuilderInterface $builder
     * @param string $name internal name of the element
     * @param string $label translation key for the label of the pdf button
     * @param array $parameters parameters for the translation
     * @return void
     */
    private function addPDFfield(FormBuilderInterface $builder, string $name, string $label, array $parameters = []): void {
        $builder->add($name,FileType::class,['required' => false, 'label' => $label, self::labelParams => $parameters]);
    }
}