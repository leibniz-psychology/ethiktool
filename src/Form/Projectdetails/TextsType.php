<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class TextsType extends TypeAbstract
{
    use ProjectdetailsTrait;

    private bool $isBurdensRisks;
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $dummyParams = $options[self::dummyParams];
        $this->isBurdensRisks = $dummyParams['isBurdensRisks'];
        $translationPrefix = 'projectdetails.pages.texts.';
        $templateArray = [self::introNode,self::proNode,self::conNode,self::findingTextNode];
        foreach (array_merge([self::introNode,self::goalsNode,self::procedureNode,self::proNode,self::conNode],$dummyParams['isFinding'] ? [self::findingTextNode] : []) as  $type) {
            $this->addFormElement($builder,$type,'textarea',$translationPrefix.$type.'.title');
            if (in_array($type,$templateArray)) {
                $this->addFormElement($builder,$type.'Template','checkbox',$translationPrefix.'useTemplate');
            }
        }
        // additional pro text field
        $information = $options[self::informationNode];
        $this->addFormElement($builder,self::proTemplateText,'text',$translationPrefix.'pro.template.start',[self::labelParams => [self::addressee => $options[self::addresseeType], self::informationNode => $information==='' || $information==='pre' ? 'pre' : 'post']]);
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // intro
        $tempArray = $viewData[self::introNode];
        $forms[self::introTemplate]->setData($this->getBoolFromString($tempArray[self::introTemplate]));
        $forms[self::introNode]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        // goals and procedure
        $forms[self::goalsNode]->setData($viewData[self::goalsNode]);
        $forms[self::procedureNode]->setData($viewData[self::procedureNode]);
        // pro
        $tempArray = $viewData[self::proNode];
        $forms[self::proTemplate]->setData($this->getBoolFromString($tempArray[self::proTemplate]));
        $forms[self::proTemplateText]->setData($this->getArrayValue($tempArray,self::proTemplateText));
        $forms[self::proNode]->setData($tempArray[self::descriptionNode]);
        // con
        $tempArray = $viewData[self::conNode];
        $forms[self::conTemplate]->setData($this->getBoolFromString($tempArray[self::conTemplate]));
        $forms[self::conNode]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        // finding consent
        if (array_key_exists(self::findingTextNode,$viewData)) {
            $tempArray = $viewData[self::findingTextNode];
            $forms[self::findingTemplate]->setData($this->getBoolFromString($tempArray[self::findingTemplate]));
            $forms[self::findingTextNode]->setData($this->getArrayValue($tempArray,self::descriptionNode));
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // intro, goals and procedure
        $tempVal = $forms[self::introTemplate]->getData();
        $tempArray = [self::introTemplate => $tempVal];
        if (!$tempVal) {
            $tempArray[self::descriptionNode] = $forms[self::introNode]->getData();
        }
        $newData = [self::introNode => $tempArray, self::goalsNode => $forms[self::goalsNode]->getData(), self::procedureNode => $forms[self::procedureNode]->getData()];
        // pro
        $tempVal = $forms[self::proTemplate]->getData();
        $tempArray = [self::proTemplate => $tempVal];
        if ($tempVal) {
            $tempArray[self::proTemplateText] = $forms[self::proTemplateText]->getData();
        }
        $tempArray[self::descriptionNode] = $forms[self::proNode]->getData();
        $newData[self::proNode] = $tempArray;
        // con
        $tempVal = $forms[self::conTemplate]->getData();
        $tempArray = [self::conTemplate => $tempVal];
        if (!$tempVal || $this->isBurdensRisks) {
            $tempArray[self::descriptionNode] = $forms[self::conNode]->getData();
        }
        $newData[self::conNode] = $tempArray;
        // finding consent
        if (array_key_exists(self::findingTextNode,$forms)) {
            $tempVal = $forms[self::findingTemplate]->getData();
            $newData[self::findingTextNode] = array_merge([self::findingTemplate => $tempVal],!$tempVal ? [self::descriptionNode => $forms[self::findingTextNode]->getData()] : []);
        }
        $viewData = $newData;
    }
}