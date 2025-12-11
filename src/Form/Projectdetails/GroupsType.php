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
        $this->addCheckboxGroup($builder,self::examinedTypes,$translationPrefix.'examined.types.',textareaName: self::peopleDescription);
        // closed group
        $tempPrefix = $translationPrefix.'closed.';
        $this->addBinaryRadio($builder,self::closedNode,$tempPrefix.'title');
        $this->addCheckboxGroup($builder,self::closedTypes,$tempPrefix.'types.',self::closedOtherText,$tempPrefix.'types.placeholder');
        // criteria
        if ($options[self::dummyParams]['hasCriteria']) { // criteria may not be asked even if the review process says so
            foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
                $this->addCheckboxTextfield($builder,$type,$translationPrefix.'criteria.'.$type.'.noCriteria');
            }
        }
        // sample size
        $tempPrefix = $translationPrefix.'sampleSize.';
        $this->addFormElement($builder,self::sampleSizeTotalNode,'spinner',options: $this->setMinMax(1,99999));
        $this->addFormElement($builder,self::sampleSizeFurtherNode,'textarea',hint: $tempPrefix.'furtherParticulars');
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
        $this->setSelectedCheckboxes($forms,$viewData[self::examinedPeopleNode]);
        // people description
        $forms[self::peopleDescription]->setData($this->getArrayValue($viewData,self::peopleDescription));
        // closed group
        $tempArray = $viewData[self::closedNode];
        $chosen = $tempArray[self::chosen];
        $forms[self::closedNode]->setData($chosen);
        if ($chosen==='0') {
            $types = $tempArray[self::closedTypesNode];
            if ($types!=='') {
                $this->setSelectedCheckboxes($forms,$types,[self::closedOther => self::closedOtherText]);
            }
        }
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
        $this->setSelectedCheckboxes($forms,$viewData[self::recruitment],array_combine(self::recruitmentTypesOther,$this->createPrefixArray(self::recruitmentTypesOther)));
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
        $tempArray = $this->getSelectedCheckboxes($forms,self::examinedTypes);
        if ($minAge!==null && $minAge<16) { // if min age is smaller than 16, wards is selected, but disabled, i.e., is not get submitted
            $tempArray = array_merge($tempArray,[self::wardsExaminedNode => '']);
        }
        $newData[self::examinedPeopleNode] = $tempArray;
        // people description
        $numSelected = count($tempArray);
        if ($minAge<18 || $numSelected>1 || $numSelected===1 && !array_key_exists(self::healthyExaminedNode,$tempArray)) {
            $newData[self::peopleDescription] = $forms[self::peopleDescription]->getData();
        }
        // closed group
        $chosen = $forms[self::closedNode]->getData();
        $newData[self::closedNode] = array_merge([self::chosen => $chosen],$chosen===0 ? [self::closedTypesNode => $this->getSelectedCheckboxes($forms,self::closedTypes,[self::closedOther => self::closedOtherText])] : []);
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
                    foreach (explode("\n",$curCriteria) as $index => $criterion) {
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
        $newData[self::recruitment] = $this->getSelectedCheckboxes($forms,self::recruitmentTypes,array_combine(self::recruitmentTypesOther,$this->createPrefixArray(self::recruitmentTypesOther)));
        if (array_key_exists(self::recruitmentFurther,$forms)) {
            $newData[self::recruitmentFurther] = $forms[self::recruitmentFurther]->getData();
        }
        $viewData = $newData;
    }
}