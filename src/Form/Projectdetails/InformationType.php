<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class InformationType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $pagePrefix = 'projectdetails.pages.information.';
        $translationPrefix = $pagePrefix.self::pre.'.';
        $dummyParams = $options[self::dummyParams];
        $isInformation = $dummyParams['isInformation'];
        $addresseeParam = [self::addressee => $options[self::addresseeString]];
        $parameters = [self::labelParams => array_merge($addresseeParam,[self::addresseeType => $options[self::addresseeType], self::participant => $options[self::participantsString], 'isInformation' => $this->getStringFromBool($isInformation)])];
        foreach ([self::pre,self::post,self::preComplete] as $type) {
            $this->addBinaryRadio($builder,$type,$pagePrefix.$type.'.title',$this->appendText($type),$type!==self::preComplete ? $pagePrefix.self::textHintPlural.'.'.$type : '',options: $parameters); // text hint for preCompleteType is set in template
            $this->addRadioGroup($builder,$type.'Type',self::informationTypes,$pagePrefix.'type.title',options: [self::labelParams => ['type' => $type]]);
        }
        // additional widgets for yes
        $this->addRadioGroup($builder,self::preContent,$this->translateArray($translationPrefix.'content.type.',array_merge([self::complete],self::preContentIncomplete)),$translationPrefix.'content.title',options: $parameters);
        $this->addRadioGroup($builder,self::preAbort,self::preAbortTypes,$pagePrefix.self::preAbort.'.title',self::preAbort.self::descriptionCap,options: $parameters);
        // attendance
        if ($dummyParams['isAttendance']) {
            $this->addBinaryRadio($builder,self::attendanceNode,$pagePrefix.self::attendanceNode,options: [self::labelParams => $addresseeParam]);
        }
        // document translation
        $reviewProcess = $dummyParams[self::reviewProcess];
        if ($isInformation && str_contains($reviewProcess,self::reviewProcessFull)) {
            $tempPrefix = $pagePrefix.self::documentTranslationNode.'.';
            $this->addBinaryRadio($builder,self::documentTranslationNode,$tempPrefix.'title',textName: self::documentTranslationNode.self::descriptionCap,textHint: $tempPrefix.self::textHint);
            if ($reviewProcess===self::reviewFullDocs) {
                $this->addFormElement($builder,self::documentTranslationPDF,'checkbox',$tempPrefix.'pdf.title');
            }
        }
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        $pre = $viewData[self::pre];
        $forms[self::pre]->setData($pre);
        if ($pre!=='') {
            if ($pre==='0') {
                if (array_key_exists(self::preType,$forms)) {
                    $forms[self::preType]->setData($viewData[self::preType]); // type of pre information
                }
                // pre content
                $tempVal = $viewData[self::preContent];
                $forms[self::preContent]->setData($tempVal);
                if (in_array($tempVal,self::preContentIncomplete)) { // partial or deceit
                    $this->setChosenArray($forms,$viewData,self::preComplete,[self::preCompleteType => self::preCompleteType,self::descriptionNode => self::preCompleteText]); // complete post
                    $tempArray = $viewData[self::preComplete];
                    if (array_key_exists(self::preAbort,$tempArray)) {
                        $this->setChosenArray($forms,$tempArray,self::preAbort,[self::descriptionNode => self::preAbort.self::descriptionCap]);
                    }
                }
            } elseif ($pre==='1') {
                $forms[self::preText]->setData($viewData[self::preText]); // description
                $tempArray = $viewData[self::post];
                $tempVal = $tempArray[self::chosen];
                $forms[self::post]->setData($tempVal);
                if ($tempVal!=='') {
                    $forms[$tempVal==='0' ? self::postType : self::postText]->setData($tempArray[self::descriptionNode]);
                }
            }
        }
        // attendance
        if (array_key_exists(self::attendanceNode,$forms)) {
            $forms[self::attendanceNode]->setData($viewData[self::attendanceNode]);
        }
        // document translation
        $this->setChosenArray($forms,$viewData,self::documentTranslationNode,[self::descriptionNode => self::documentTranslationNode.self::descriptionCap]);
        if (array_key_exists(self::documentTranslationPDF,$forms)) {
            $forms[self::documentTranslationPDF]->setData(array_key_exists(self::documentTranslationPDF,$viewData));
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $pre = $forms[self::pre]->getData();
        $isPre = $pre===0;
        $isPost = false;
        $newData = [self::pre => $pre];
        $informationType = ''; // type of pre/post information
        if ($isPre) { // pre information
            if (array_key_exists(self::preType,$forms)) {
                $informationType = $forms[self::preType]->getData();
                $newData[self::preType] = $informationType; // type of pre information
            }
            // pre content
            $tempVal = $forms[self::preContent]->getData();
            $newData[self::preContent] = $tempVal;
            if (in_array($tempVal,self::preContentIncomplete)) { // partial or deceit
                $tempVal = $forms[self::preComplete]->getData(); // complete post
                $tempArray = [self::chosen => $tempVal];
                if ($tempVal!==null) {
                    if ($tempVal===0) {
                        $tempArray[self::preCompleteType] = $forms[self::preCompleteType]->getData(); // type of complete post
                        $tempArray[self::preAbort] = $this->getChosenArray($forms,self::preAbort,self::preAbortDescriptions,[self::descriptionNode => self::preAbort.self::descriptionCap]); // debriefing if withdrawal
                    }
                    $tempArray[self::descriptionNode] = $forms[self::preCompleteText]->getData(); // description of which information is given when
                }
                $newData[self::preComplete] = $tempArray;
            }
        } elseif ($pre===1) {
            $newData[self::preText] = $forms[self::preText]->getData(); // description
            $tempVal = $forms[self::post]->getData();
            $tempArray = [self::chosen => $tempVal];
            $isPost = $tempVal===0;
            if ($tempVal!==null) {
                $tempVal = $forms[$isPost ? self::postType : self::postText]->getData();
                $tempArray[self::descriptionNode] = $tempVal; // type of post information or description
                if ($isPost) {
                    $informationType = $tempVal;
                }
            }
            $newData[self::post] = $tempArray;
        }
        // attendance
        if (array_key_exists(self::attendanceNode,$forms)) {
            $newData[self::attendanceNode] = $forms[self::attendanceNode]->getData();
        }
        // document translation
        if (array_key_exists(self::documentTranslationNode,$forms) && $informationType!==self::informationOral && ($isPre || $isPost)) {
            $newData[self::documentTranslationNode] = $this->getChosenArray($forms,self::documentTranslationNode,0,[self::descriptionNode => self::documentTranslationNode.self::descriptionCap]);
            if ($newData[self::documentTranslationNode][self::chosen]===0 && array_key_exists(self::documentTranslationPDF,$forms) && $forms[self::documentTranslationPDF]->getData()) {
                $newData[self::documentTranslationPDF] = '';
            }
        }
        $viewData = $newData;
    }
}