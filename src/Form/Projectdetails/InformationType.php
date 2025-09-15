<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class InformationType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $pagePrefix = 'projectdetails.pages.information.';
        $translationPrefix = $pagePrefix.self::pre.'.';
        $addresseeParam = [self::addressee => $options[self::addresseeString]];
        $parameters = [self::labelParams => array_merge($addresseeParam,[self::participant => $options[self::participantsString]])];
        foreach ([self::pre,self::post,self::preComplete] as $type) {
            $this->addBinaryRadio($builder,$type,$pagePrefix.$type.'.title',$this->appendText($type),$pagePrefix.self::textHintPlural.'.'.$type,array_merge($parameters,$type===self::preComplete ? ['attr' => ['placeholder' => '']] : [])); // placeholder for preCompleteType is set in template
            $this->addRadioGroup($builder,$type.'Type',self::informationTypes,$pagePrefix.'type.title',options: [self::labelParams => ['type' => $type]]);
        }
        // additional widgets for yes
        $this->addRadioGroup($builder,self::preContent,$this->translateArray($translationPrefix.'content.type.',array_merge([self::complete],self::preContentIncomplete)),$translationPrefix.'content.title',options: $parameters);
        // attendance
        $dummyParams = $options[self::dummyParams];
        if ($dummyParams['isAttendance']) {
            $this->addBinaryRadio($builder,self::attendanceNode,$pagePrefix.self::attendanceNode,options: [self::labelParams => $addresseeParam]);
        }
        // document translation
        if ($dummyParams['isInformation']) {
            $tempPrefix = $pagePrefix.self::documentTranslationNode.'.';
            $this->addBinaryRadio($builder,self::documentTranslationNode,$tempPrefix.'title');
            $this->addFormElement($builder,self::documentTranslationNode.self::descriptionCap,'text',hint: $tempPrefix.self::textHint);
            $this->addFormElement($builder,self::documentTranslationPDF,'checkbox',$tempPrefix.'pdf.title');
        }
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $pre = $viewData[self::chosen];
        $forms[self::pre]->setData($pre);
        if ($pre!='') {
            $isPre = $pre==='0';
            $forms[$isPre ? self::preType : self::preText]->setData($viewData[self::descriptionNode]); // type or explanation
            $tempArray = $viewData[self::informationAddNode];
            $contentChosen = $tempArray[self::chosen]; // content or post
            $forms[$isPre ? self::preContent : self::post]->setData($contentChosen);
            if (in_array($contentChosen,self::preContentIncomplete)) {
                $forms[self::preComplete]->setData($tempArray[self::complete]);
                $forms[self::preCompleteType]->setData($this->getArrayValue($tempArray,self::preCompleteType));
                $forms[self::preCompleteText]->setData($tempArray[self::descriptionNode]);
            }
            elseif (!$isPre && $contentChosen!=='') { // no pre information and selection of post information
                $forms[$contentChosen==='0' ? self::postType : self::postText]->setData($tempArray[self::descriptionNode]);
            }
        }
        // attendance
        if (array_key_exists(self::attendanceNode,$forms)) {
            $forms[self::attendanceNode]->setData($viewData[self::attendanceNode]);
        }
        // document translation
        if (array_key_exists(self::documentTranslationNode,$viewData)) {
            $tempArray = $viewData[self::documentTranslationNode];
            $forms[self::documentTranslationNode]->setData($tempArray[self::chosen]);
            $forms[self::documentTranslationNode.self::descriptionCap]->setData($tempArray[self::descriptionNode] ?? '');
            $forms[self::documentTranslationPDF]->setData(array_key_exists(self::documentTranslationPDF,$tempArray));
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $pre = $forms[self::pre]->getData();
        $post = $forms[self::post]->getData();
        $newData[self::chosen] = $pre;
        $tempArray = [self::chosen => ''];
        $isPre = $pre===0; // selection was yes
        if ($pre!==null) { // a selection was made
            $newData[self::descriptionNode] = $forms[$isPre ? self::preType : self::preText]->getData(); // type or explanation
            $contentChosen = $isPre ? $forms[self::preContent]->getData() : $post; // content or post
            $tempArray[self::chosen] = $contentChosen;
            if ($isPre) {
                if (in_array($contentChosen,self::preContentIncomplete)) { // pre information and incomplete/wrong information
                    $chosen = $forms[self::preComplete]->getData(); // complete information afterward
                    $tempArray[self::complete] = $chosen;
                    if ($chosen===0) {
                        $tempArray[self::preCompleteType] = $forms[self::preCompleteType]->getData();
                    }
                    $tempArray[self::descriptionNode] = $forms[self::preCompleteText]->getData();
                }
            }
            elseif ($contentChosen!==null) { // no pre information and selection of post information
                $tempArray[self::descriptionNode] = $forms[$contentChosen===0 ? self::postType : self::postText]->getData();
            }
        }
        $newData[self::informationAddNode] = $tempArray;
        // attendance
        if (array_key_exists(self::attendanceNode,$forms)) {
            $newData[self::attendanceNode] = $forms[self::attendanceNode]->getData();
        }
        // document translation
        if (array_key_exists(self::documentTranslationNode,$forms) && ($isPre || $post===0)) {
            $tempVal = $forms[self::documentTranslationNode]->getData();
            $tempArray = [self::chosen => $tempVal];
            if ($tempVal===0) {
                $tempArray[self::descriptionNode] = $forms[self::documentTranslationNode.self::descriptionCap]->getData();
                if ($forms[self::documentTranslationPDF]->getData()) {
                    $tempArray[self::documentTranslationPDF] = '';
                }
            }
            $newData[self::documentTranslationNode] = $tempArray;
        }
        $viewData = $newData;
    }
}