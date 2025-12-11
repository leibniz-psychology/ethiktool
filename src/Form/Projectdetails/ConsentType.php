<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class ConsentType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dummyParams = $options[self::dummyParams];
        $addressee = $options[self::addresseeType];
        $isNotParticipants = $addressee!==self::addresseeParticipants;
        $translationPrefix = 'projectdetails.pages.consent.';
        foreach ([self::consentNode,self::voluntaryNode] as $type) {
            $types = $type===self::consentNode ? self::consentTypes : self::voluntaryTypes;
            foreach (array_merge([''],$isNotParticipants ? ['Participants'] : []) as $curAddressee) {
                $isThirdParty = $curAddressee==='';
                $this->addRadioGroup($builder,$type.$curAddressee,$types,$translationPrefix.self::addressee.'.'.($isThirdParty ? $addressee : 'participants'),$isThirdParty ? $type.self::descriptionCap : '', $isThirdParty ? $translationPrefix.$type.'.'.self::textHint : '');
                $this->addFormElement($builder,self::consentOtherDescription.$curAddressee,'text',options: ['attr' => ['placeholder' => $translationPrefix.self::consentNode.'.types.'.self::consentOtherDescription]]);
            }
        }
        if ($dummyParams['isClosedDependent']) {
            $this->addFormElement($builder,self::voluntaryYesDescription,'textarea',hint: $translationPrefix.self::voluntaryNode.'.textHintYes');
        }
        // terminate with disadvantages
        $tempPrefix = $translationPrefix.self::terminateConsNode.'.';
        $this->addBinaryRadio($builder,self::terminateConsNode,$tempPrefix.'title',self::terminateConsNode.self::descriptionCap,$tempPrefix.self::textHint,[self::labelParams => [self::addressee => $this->translateString('projectdetails.addressee.'.($isNotParticipants ? ($dummyParams['isAttendance'] ? 'both' : 'participants') : 'thirdParties').'.'.$addressee)]]);
        $information = $options[self::informationNode];
        if ($information===self::pre) {
            $this->addFormElement($builder,self::terminateConsParticipationNode,'textarea',hint: $translationPrefix.self::terminateConsNode.'.participation');
        }
        // termination by participants
        if ($dummyParams['hasTerminateParticipants']) { // terminate by participants may not be asked even if the review process says so
            $tempPrefix = $translationPrefix.self::terminateParticipantsNode.'.';
            $this->addRadioGroup($builder,self::terminateParticipantsNode,self::terminateParticipantsTypes,$tempPrefix.'title',$this->appendText(self::terminateParticipantsNode),$tempPrefix.self::textHint,[self::labelParams => [self::informationNode => $information]]);
        }
        // termination criteria
        $this->addFormElement($builder,self::terminateCriteriaNode,'textarea',hint: $translationPrefix.'terminateCriteria.'.self::textHint);
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // voluntary and consent
        $otherDescription = self::consentOtherDescription.'Participants';
        foreach ([self::voluntaryNode,self::consentNode] as $type) {
            $this->setChosenArray($forms,$viewData,$type,array_merge([self::chosen2Node => $type.'Participants', self::descriptionNode => $type.self::descriptionCap],$type===self::voluntaryNode ? [self::voluntaryYesDescription => self::voluntaryYesDescription] : [$otherDescription => $otherDescription]));
        }
        // terminate cons
        $this->setChosenArray($forms,$viewData,self::terminateConsNode,[self::descriptionNode => self::terminateConsNode.self::descriptionCap]);
        if (array_key_exists(self::terminateConsParticipationNode,$forms)) {
            $forms[self::terminateConsParticipationNode]->setData($this->getArrayValue($viewData,self::terminateConsParticipationNode));
        }
        // termination by participants
        $this->setChosenArray($forms,$viewData,self::terminateParticipantsNode,[self::descriptionNode => $this->appendText(self::terminateParticipantsNode)]);
        // terminate criteria
        if (array_key_exists(self::terminateCriteriaNode,$forms)) {
            $forms[self::terminateCriteriaNode]->setData($viewData[self::terminateCriteriaNode]);
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        foreach ([self::voluntaryNode,self::consentNode] as $type) {
            $tempArray = [];
            foreach (array_merge([''],array_key_exists($type.'Participants',$forms) ? ['Participants'] : []) as $addressee) {
                $chosen = $forms[$type.$addressee]->getData();
                $tempArray[$addressee==='' ? self::chosen : self::chosen2Node] = $chosen;
                if ($type===self::consentNode && $chosen===self::consentOther) {
                    $otherDescription = self::consentOtherDescription.$addressee;
                    $tempArray[$otherDescription] = $forms[$otherDescription]->getData();
                }
            }
            if (in_array(self::voluntaryConsentNo,$tempArray)) {
                $tempArray[self::descriptionNode] = $forms[$type.self::descriptionCap]->getData();
            }
            if ($type===self::voluntaryNode && array_key_exists(self::voluntaryYesDescription,$forms) && in_array('yes',[$tempArray[self::chosen],$tempArray[self::chosen2Node] ?? ''])) {
                $tempArray[self::voluntaryYesDescription] = $forms[self::voluntaryYesDescription]->getData();
            }
            $newData[$type] = $tempArray;
        }
        // terminate cons
        $newData[self::terminateConsNode] = $this->getChosenArray($forms,self::terminateConsNode,1,[self::descriptionNode => self::terminateConsNode.self::descriptionCap]);
        if ($newData[self::terminateConsNode][self::chosen]===1 && array_key_exists(self::terminateConsParticipationNode,$forms)) { // answer was 'no'
            $newData[self::terminateConsParticipationNode] = $forms[self::terminateConsParticipationNode]->getData();
        }
        // termination by participants
        if (array_key_exists(self::terminateParticipantsNode,$forms)) {
            $newData[self::terminateParticipantsNode] = $this->getChosenArray($forms,self::terminateParticipantsNode,self::terminateParticipantsOther,[self::descriptionNode => $this->appendText(self::terminateParticipantsNode)]);
        }
        // terminate criteria
        if (array_key_exists(self::terminateCriteriaNode,$forms)) {
            $newData[self::terminateCriteriaNode] = $forms[self::terminateCriteriaNode]->getData();
        }
        $viewData = $newData;
    }
}