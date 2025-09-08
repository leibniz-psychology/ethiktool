<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class ConsentType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
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
            $this->addFormElement($builder,self::terminateConsNode.self::terminateConsParticipationNode,'textarea',hint: $translationPrefix.self::terminateConsNode.'.participation');
        }
        // termination by participants
        $tempPrefix = $translationPrefix.self::terminateParticipantsNode.'.';
        $this->addRadioGroup($builder,self::terminateParticipantsNode,self::terminateParticipantsTypes,$tempPrefix.'title',$this->appendText(self::terminateParticipantsNode),$tempPrefix.self::textHint,[self::labelParams => [self::informationNode => $information]]);
        // termination criteria
        $this->addFormElement($builder,self::terminateCriteriaNode,'textarea',hint: $translationPrefix.'terminateCriteria.'.self::textHint);
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // voluntary and consent
        foreach ([self::voluntaryNode,self::consentNode] as $type) {
            $tempArray = $viewData[$type];
            $forms[$type]->setData($tempArray[self::chosen]);
            foreach (array_merge([''],array_key_exists(self::chosen2Node,$tempArray) ? ['Participants'] : []) as $addressee) {
                $forms[$type.$addressee]->setData($tempArray[$addressee==='' ? self::chosen : self::chosen2Node]);
                $otherDescription = self::consentOtherDescription.$addressee;
                $forms[$otherDescription]->setData($tempArray[$otherDescription] ?? '');
            }
            if (array_key_exists(self::descriptionNode,$tempArray)) {
                $forms[$type.self::descriptionCap]->setData($tempArray[self::descriptionNode]);
            }
            if ($type===self::voluntaryNode && array_key_exists(self::voluntaryYesDescription,$tempArray)) {
                $forms[self::voluntaryYesDescription]->setData($tempArray[self::voluntaryYesDescription]);
            }
        }
        // terminate with disadvantages
        $tempVal = self::terminateConsNode.self::terminateConsParticipationNode;
        $this->setChosenArray($forms,$viewData,self::terminateConsNode,array_merge([self::descriptionNode => self::terminateConsNode.self::descriptionCap],array_key_exists($tempVal,$forms) ? [self::terminateConsParticipationNode => $tempVal] : []),true);
        // termination by participants
        $this->setChosenArray($forms,$viewData,self::terminateParticipantsNode,[self::descriptionNode => $this->appendText(self::terminateParticipantsNode)],true);
        // terminate criteria
        $forms[self::terminateCriteriaNode]->setData($viewData[self::terminateCriteriaNode]);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
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
        // terminate with disadvantages
        $tempArray = $this->getChosenArray($forms,self::terminateConsNode,1,[self::descriptionNode => self::terminateConsNode.self::descriptionCap],true);
        $tempVal = self::terminateConsNode.self::terminateConsParticipationNode;
        if ($tempArray[self::chosen]===1 && array_key_exists($tempVal,$forms)) {
            $tempArray[self::terminateConsParticipationNode] = $forms[self::terminateConsNode.self::terminateConsParticipationNode]->getData();
        }
        $newData[self::terminateConsNode] = $tempArray;
        // termination by participants
        $newData[self::terminateParticipantsNode] = $this->getChosenArray($forms,self::terminateParticipantsNode,self::terminateParticipantsOther,[self::descriptionNode => $this->appendText(self::terminateParticipantsNode)],true);
        // terminate criteria
        $newData[self::terminateCriteriaNode] = $forms[self::terminateCriteriaNode]->getData();
        $viewData = $newData;
    }
}