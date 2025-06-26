<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;

class DataReuseType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.dataReuse.';
        $dummyParams = $options[self::dummyParams];
        $personal = $dummyParams['personal'];
        $personalParam = ['personal' => $personal];
        // confirm
        $this->addFormElement($builder,self::confirmIntroNode,'checkbox',$translationPrefix.self::introNode.'.confirm');
        $isDataReuseHowTwice = $personal==='purpose' && $dummyParams['isAnonymized'];
        if (in_array($personal,['personal','immediately','keep','marking','anonymous']) || $isDataReuseHowTwice || $personal==='noTool') {
            $headingParams = [self::labelParams => $personalParam];
            // data reuse
            $this->addRadioGroup($builder,self::dataReuseNode,self::dataReuseTypes[$dummyParams[self::dataReuseNode]],$dummyParams['dataReuseHeading'][$isDataReuseHowTwice ? self::personalKeepReuse : '']);
            // reuse self
            $this->addBinaryRadio($builder,self::dataReuseSelfNode,$translationPrefix.self::dataReuseSelfNode.'.title',options: $headingParams);
        }
        // reuse how
        $hint = $translationPrefix.self::dataReuseHowNode.'.hints.textHint';
        $isNotPurposeReuse = !$dummyParams['isPurposeReuse'];
        foreach (array_merge([''],$isDataReuseHowTwice ? [self::personalKeepReuse] : []) as $suffix) {
            $tempVal = self::dataReuseHowNode.$suffix;
            if ($suffix===self::personalKeepReuse) { // anonymized research data
                $personalParam['personal'] = 'keep'; // use one of the keys for anonymized data, but not 'keep'
            }
            $this->addRadioGroup($builder,$tempVal,array_diff(self::dataReuseHowTypes,$isNotPurposeReuse || $suffix===self::personalKeepReuse ? self::dataReuseHowOwn : []));
            $this->addFormElement($builder,$tempVal.self::descriptionCap,'text',hint: $hint);
        }
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // confirm
        $forms[self::confirmIntroNode]->setData($viewData[self::confirmIntroNode]==='1');
        // data reuse
        if (array_key_exists(self::dataReuseNode, $forms)) {
            $forms[self::dataReuseNode]->setData($this->getArrayValue($viewData,self::dataReuseNode));
            // data reuse self
            $forms[self::dataReuseSelfNode]->setData($this->getArrayValue($viewData,self::dataReuseSelfNode));
        }
        // data reuse how
        foreach (['',self::personalKeepReuse] as $suffix) {
            $tempVal = self::dataReuseHowNode.$suffix;
            if (array_key_exists($tempVal, $viewData)) {
                $tempArray = $viewData[$tempVal];
                $forms[$tempVal]->setData($this->getArrayValue($tempArray,self::chosen));
                $forms[$tempVal.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            }
        }
    }

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // confirm
        $tempVal = $forms[self::confirmIntroNode]->getData();
        $newData = [self::confirmIntroNode => $tempVal];
        if ($tempVal) {
            // data reuse
            $dataReuse = '';
            $isReuseAsked = array_key_exists(self::dataReuseNode,$forms);
            if ($isReuseAsked) {
                $dataReuse = $forms[self::dataReuseNode]->getData();
                $newData[self::dataReuseNode] = $dataReuse;
            }
            // data reuse self
            if ($dataReuse==='no') {
                $newData[self::dataReuseSelfNode] = $forms[self::dataReuseSelfNode]->getData();
            }
            // data reuse how
            $isReuseHowTwice = array_key_exists(self::dataReuseHowNode.self::personalKeepReuse,$forms);
            foreach (['',self::personalKeepReuse] as $suffix) {
                $dataReuseHow = self::dataReuseHowNode.$suffix;
                $isReuseOther = in_array($dataReuse,['yes','anonymous','anonymized','personal']);
                if (array_key_exists($dataReuseHow, $forms) && ($suffix==='' && ($isReuseHowTwice || $isReuseOther | !$isReuseAsked) || $isReuseOther)) {
                    $chosen = $forms[$dataReuseHow]->getData();
                    $tempArray = [self::chosen => $chosen];
                    if ($chosen!=='own') {
                        $tempArray[self::descriptionNode] = $forms[$dataReuseHow.self::descriptionCap]->getData();
                    }
                    $newData[$dataReuseHow] = $tempArray;
                }
            }
        }
        $viewData = $newData;
    }
}