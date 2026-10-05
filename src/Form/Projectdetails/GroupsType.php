<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class GroupsType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translationPrefix = 'projectdetails.pages.groups.';
        // age
        $tempPrefix = $translationPrefix.'age.';
        $this->addFormElement($builder,self::minAge,'spinner',$tempPrefix.self::minAge,options: $this->setMinMax(0,100));
        $this->addFormElement($builder,self::maxAge,'spinner',$tempPrefix.self::maxAge,options: $this->setMinMax(1,100));
        $this->addFormElement($builder,self::unlimited,'checkbox',$tempPrefix.'unlimited');
        // examined
        $tempPrefix = $translationPrefix.'examined.';
        $this->addCheckboxGroup($builder,self::examinedTypes,$tempPrefix.'types.',$this->createPrefixArray(self::examinedTypesOther),array_fill_keys(self::examinedTypesOther,$tempPrefix.'placeholder'),textareaName: self::peopleDescription);
        $this->addBinaryRadio($builder,self::diseaseNode,$translationPrefix.self::diseaseNode.'.title');
        // criteria
        if ($options[self::dummyParams]['hasCriteria']) { // criteria may not be asked even if the review process says so
            foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
                $this->addCheckboxTextfield($builder,$type,$translationPrefix.'criteria.'.$type.'.noCriteria');
            }
        }
        // sample size
        $tempPrefix = $translationPrefix.'sampleSize.';
        $this->addFormElement($builder,self::sampleSizeTotalNode,'spinner',options: $this->setMinMax(1,99999));
        $this->addFormElement($builder,self::sampleSizeFurtherNode,'text',hint: $tempPrefix.'furtherParticulars');
        $this->addFormElement($builder,self::sampleSizePlanNode,'textarea',hint: $tempPrefix.'plan.textHint');
        // recruitment
        $tempPrefix = $translationPrefix.'recruitment.types.';
        $this->addCheckboxGroup($builder,self::recruitmentTypes,$tempPrefix,$this->createPrefixArray(self::recruitmentTypesOther),array_fill_keys(self::recruitmentTypes,$tempPrefix.'otherDescription'),self::recruitmentFurther);
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // age
        $this->setSpinner($forms,$viewData,[self::minAge,self::maxAge],exclude: '-1');
        $forms[self::unlimited]->setData($viewData[self::maxAge]==='-1');
        // examined
        $this->setSelectedCheckboxesMultiLevel($forms,$viewData[self::examinedPeopleNode]);
        // disease
        $forms[self::diseaseNode]->setData($this->getArrayValue($viewData,self::diseaseNode));
        // people description
        $forms[self::peopleDescription]->setData($this->getArrayValue($viewData,self::peopleDescription));
        // criteria
        if (array_key_exists(self::criteriaIncludeNode,$forms)) { // either both include and exclude or neither exist
            foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
                $tempArray = $viewData[$type];
                $forms[$type]->setData($this->getBoolFromString($tempArray[self::noCriteriaNode]));
                $criteriaString = '';
                $tempArray = $tempArray[self::criteriaNode];
                if ($tempArray!=='') { // at least one criterion
                    foreach ($tempArray as $criterion) {
                        $criteriaString .= $criterion."\n";
                    }
                }
                $forms[$this->appendText($type)]->setData($criteriaString);
            }
        }
        // sample size
        if (array_key_exists(self::sampleSizeTotalNode,$forms)) {
            $tempArray = $viewData[self::sampleSizeNode];
            $this->setSpinner($forms,$tempArray,self::sampleSizeTotalNode);;
            $forms[self::sampleSizeFurtherNode]->setData($tempArray[self::sampleSizeFurtherNode]);
            $forms[self::sampleSizePlanNode]->setData($tempArray[self::sampleSizePlanNode]);
        }
        // recruitment
        $this->setSelectedCheckboxesMultiLevel($forms,$viewData[self::recruitment]);
        if (array_key_exists(self::recruitmentFurther,$forms)) {
            $forms[self::recruitmentFurther]->setData($viewData[self::recruitmentFurther]);
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        // age
        $minAge = $forms[self::minAge]->getData();
        $newData = [self::minAge => $minAge];
        $newData[self::maxAge] = $forms[self::unlimited]->getData() ? '-1' : $forms[self::maxAge]->getData();
        $tempArray = $this->getSelectedCheckboxesMultiLevel($forms,self::examinedTypesAll,self::examinedTypesOther);
        if ($minAge!==null && $minAge<14) { // if min age is smaller than 14, wards is selected, but disabled, i.e., is not submitted
            $tempArray = array_merge($tempArray,[self::wardsExaminedNode => '']);
        }
        $newData[self::examinedPeopleNode] = $tempArray;
        // disease
        if (array_key_exists(self::physicalExaminedNode,$tempArray)) {
            $newData[self::diseaseNode] = $forms[self::diseaseNode]->getData();
        }
        // people description
        $numSelected = count($tempArray);
        if ($minAge!==null && $minAge<18 || $numSelected>1 || $numSelected===1 && !array_key_exists(self::healthyExaminedNode,$tempArray)) {
            $newData[self::peopleDescription] = $forms[self::peopleDescription]->getData();
        }
        // criteria
        if (array_key_exists(self::criteriaIncludeNode,$forms)) { // either both include and exclude or neither exist
            // if checked, text area is disabled, i.e., not submitted
            foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
                $noCriteria = $forms[$type]->getData();
                $tempArray = [self::noCriteriaNode => $noCriteria];
                $curCriteria = $noCriteria ? ($type===self::criteriaIncludeNode ? $viewData[self::criteriaIncludeNode][self::criteriaNode][self::criteriaIncludeNode.'0'] : '') : $forms[$this->appendText($type)]->getData();
                $criteriaArray = ''; // if no criteria, add an empty string and not an empty array
                if ($curCriteria!=='') {
                    $criteriaArray = [];
                    foreach (explode("\n",(string) $curCriteria) as $index => $criterion) {
                        $criteriaArray[$type.$index] = trim($criterion);
                    }
                }
                $tempArray[self::criteriaNode] = $criteriaArray;
                $newData[$type] = $tempArray;
            }
        }
        // sample size
        if (array_key_exists(self::sampleSizeTotalNode,$forms)) {
            $newData[self::sampleSizeNode] = [self::sampleSizeTotalNode => $forms[self::sampleSizeTotalNode]->getData(),
                                              self::sampleSizeFurtherNode => $forms[self::sampleSizeFurtherNode]->getData(),
                                              self::sampleSizePlanNode => $forms[self::sampleSizePlanNode]->getData()];
        }
        // recruitment
        $newData[self::recruitment] = $this->getSelectedCheckboxesMultiLevel($forms,self::recruitmentTypesAll,self::recruitmentTypesOther);
        if (array_key_exists(self::recruitmentFurther,$forms)) {
            $newData[self::recruitmentFurther] = $forms[self::recruitmentFurther]->getData();
        }
        $viewData = $newData;
    }
}