<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class GroupsType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.groups.';
        // age
        $tempPrefix = $translationPrefix.'age.';
        $this->addFormElement($builder,self::minAge,'spinner',$tempPrefix.self::minAge,options: $this->setMinMax(0,100));
        $this->addFormElement($builder,self::maxAge,'spinner',$tempPrefix.self::maxAge,options: $this->setMinMax(1,100));
        $this->addFormElement($builder,self::unlimited,'checkbox',$tempPrefix.'unlimited');
        // examined
        $this->addCheckboxGroup($builder,self::examinedTypes,$translationPrefix.'examined.',textareaName: self::peopleDescription);
        // closed group
        $tempPrefix = $translationPrefix.'closed.';
        $this->addBinaryRadio($builder,self::closedNode,$tempPrefix.'title');
        $this->addCheckboxGroup($builder,self::closedTypes,$tempPrefix.'types.',self::closedOtherText,$tempPrefix.'types.placeholder');
        // criteria
        foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
            $this->addCheckboxTextfield($builder,$type,$translationPrefix.'criteria.'.$type.'.noCriteria');
        }
        // sample size
        $tempPrefix = $translationPrefix.'sampleSize.';
        $this->addFormElement($builder,self::sampleSizeTotalNode,'spinner',options: $this->setMinMax(1,99999));
        $this->addFormElement($builder,self::sampleSizeFurtherNode,'textarea',hint: $tempPrefix.'furtherParticulars');
        $this->addFormElement($builder,self::sampleSizePlanNode,'textarea',hint: $tempPrefix.'plan.textHint');
        // recruitment
        $tempPrefix = $translationPrefix.'recruitment.types.';
        $this->addCheckboxGroup($builder,self::recruitmentTypes,$tempPrefix,textHints: $tempPrefix.'otherDescription',textareaName: self::recruitmentFurther);
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // age
        $this->setSpinner($forms,$viewData,[self::minAge,self::maxAge],exclude: '-1');
        $forms[self::unlimited]->setData($viewData[self::maxAge]==='-1');
        // examined
        $this->setSelectedCheckboxes($forms,$viewData[self::examinedPeopleNode]);
        // people description
        $forms[self::peopleDescription]->setData($viewData[self::peopleDescription]);
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
        $criteria = $viewData[self::criteriaNode];
        foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
            $tempArray = $criteria[$type];
            $forms[$type]->setData($this->getBoolFromString($tempArray[self::noCriteriaNode]));
            $criteriaString = '';
            if ($criteria!=='') { // at least one criterion
                foreach ($tempArray[self::criteriaNode] ?: [] as $criterion) {
                    $criteriaString .= $criterion."\n";
                }
            }
            $forms[$this->appendText($type)]->setData($criteriaString);
        }
        // sample size
        $tempArray = $viewData[self::sampleSizeNode];
        $this->setSpinner($forms,$tempArray,self::sampleSizeTotalNode);;
        $forms[self::sampleSizeFurtherNode]->setData($tempArray[self::sampleSizeFurtherNode]);
        $forms[self::sampleSizePlanNode]->setData($tempArray[self::sampleSizePlanNode]);
        // recruitment
        $tempArray = $viewData[self::recruitment];
        $this->setSelectedCheckboxes($forms,$tempArray[self::recruitmentTypesNode]);
        $forms[self::recruitmentFurther]->setData($tempArray[self::descriptionNode]);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // age
        $minAge = $forms[self::minAge]->getData();
        $viewData[self::minAge] = $minAge;
        $viewData[self::maxAge] = $forms[self::unlimited]->getData() ? '-1' : $forms[self::maxAge]->getData();
        $tempArray = $this->getSelectedCheckboxes($forms,self::examinedTypes);
        if ($minAge!==null && $minAge<16) { // if min age is smaller than 16, wards is selected, but disabled, i.e., is not get submitted
            $tempArray = array_merge($tempArray,[self::wardsExaminedNode => '']);
        }
        $viewData[self::examinedPeopleNode] = $tempArray;
        // people description
        $viewData[self::peopleDescription] = $tempArray!==[] ? $forms[self::peopleDescription]->getData() : '';
        // closed group
        $chosen = $forms[self::closedNode]->getData();
        $viewData[self::closedNode] = array_merge([self::chosen => $chosen],$chosen===0 ? [self::closedTypesNode => $this->getSelectedCheckboxes($forms,self::closedTypes,[self::closedOther => self::closedOtherText])] : []);
        // criteria
        // if checked, text area is disabled, i.e., not submitted
        $criteria = [];
        foreach ([self::criteriaIncludeNode,self::criteriaExcludeNode] as $type) {
            $noCriteria = $forms[$type]->getData();
            $tempArray = [self::noCriteriaNode => $noCriteria];
            $curCriteria = $noCriteria ? ($type===self::criteriaIncludeNode ? $viewData[self::criteriaNode][self::criteriaIncludeNode][self::criteriaNode][self::criteriaIncludeNode.'0'] : '') : $forms[$this->appendText($type)]->getData();
            $criteriaArray = ''; // if no criteria, add an empty string and not an empty array
            if ($curCriteria!=='') {
                $criteriaArray = [];
                foreach (explode("\n",$curCriteria) as $index => $criterion) {
                    $criteriaArray[$type.$index] = trim($criterion);
                }
            }
            $tempArray[self::criteriaNode] = $criteriaArray;
            $criteria[$type] = $tempArray;
        }
        $viewData[self::criteriaNode] = $criteria;
        // sample size
        $viewData[self::sampleSizeNode] = [self::sampleSizeTotalNode => $forms[self::sampleSizeTotalNode]->getData(),
                                          self::sampleSizeFurtherNode => $forms[self::sampleSizeFurtherNode]->getData(),
                                          self::sampleSizePlanNode => $forms[self::sampleSizePlanNode]->getData()];
        // recruitment
        $viewData[self::recruitment] = [self::recruitmentTypesNode => $this->getSelectedCheckboxes($forms,self::recruitmentTypes), self::descriptionNode => $forms[self::recruitmentFurther]->getData() ?? ''];
    }
}