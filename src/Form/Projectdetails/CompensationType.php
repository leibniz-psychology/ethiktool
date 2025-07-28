<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class CompensationType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.compensation.';
        $typesPrefix = $translationPrefix.'types.';
        $textHintPrefix = $typesPrefix.self::textHintPlural.'.';
        $awardingPrefix = $translationPrefix.self::awardingNode.'.';
        // types
        $this->addCheckboxGroup($builder,self::compensationTypes,$typesPrefix,self::compensationOther.self::descriptionCap,$textHintPrefix.self::compensationOther);
        foreach (array_diff(self::compensationTypes,[self::compensationNo]) as $type) {
            if ($type!==self::compensationMoney) {
                $this->addFormElement($builder,$type.self::descriptionCap,'text',hint: $textHintPrefix.$type);
            }
            // awarding
            if ($type!==self::compensationOther) {
                $this->addRadioGroup($builder,self::awardingNode.$type,self::awardingTypes[$type]);
                $this->addFormElement($builder,$type.'otherDescription','text', options: $this->getPlaceholder($awardingPrefix.$type.'.'.$type.'OtherPlaceholder')); // other type
                $this->addFormElement($builder,$type.self::awardingLater.self::descriptionCap,'text'); // later description
                $this->addRadioGroup($builder,$type.self::laterTypesName,self::laterTypes); // later types
                $this->addFormElement($builder,$type.self::laterOtherDescription,'text',options: $this->getPlaceholder($awardingPrefix.'laterEnd.placeholder')); // description of later type other
            }
            if (in_array($type,[self::compensationMoney,self::compensationLottery])) { // external description
                $this->addFormElement($builder,$type.'externalDescription','text');
            }
        }
        // further widgets if money is selected
        $this->addBinaryRadio($builder,self::moneyFurther,$translationPrefix.self::compensationMoney.'.title',self::moneyFurther.self::descriptionCap);
        // further awarding widgets
        $tempPrefix = $awardingPrefix.self::compensationLottery.'.result.';
        $this->addFormElement($builder,self::lotteryStart.self::descriptionCap,'text', hint: $tempPrefix.self::textHint); // description of lottery start in awarding
        $this->addRadioGroup($builder,self::lotteryStart,self::lotteryTypes); // start of lottery
        $this->addFormElement($builder,self::lotteryStartOtherDescription,'text',options: $this->getPlaceholder($tempPrefix.'types.placeholder')); // description of lottery start other option
        $this->addRadioGroup($builder,'lotterydeliver'.self::descriptionCap,self::lotteryDeliverTypes); // deliver types for lottery
        $this->addRadioGroup($builder,'voucherdeliver'.self::descriptionCap,self::voucherDeliverTypes); // deliver types for voucher
        $this->addFormElement($builder,self::awardingOtherDescription,'text', hint: $awardingPrefix.self::compensationOther.'.'.self::textHint);
        foreach ([self::compensationMoney,self::compensationHours] as $type) {
            $this->addFormElement($builder,$type.self::valueSuffix, $type===self::compensationMoney ? 'money' : 'text',$typesPrefix.$type.'EndDefault');
            $this->addRadioGroup($builder,$type.self::amountSuffix,self::valueTypes);
        }
        // terminate
        $tempPrefix = $translationPrefix.self::terminateNode.'.';
        $this->addRadioGroup($builder,self::terminateNode,$this->translateArray($tempPrefix.'types.',array_merge(['complete','partial'],self::terminateTypesDescription)),$tempPrefix.'title',self::terminateNode.self::descriptionCap);
        // compensation voluntary
        $this->addBinaryRadio($builder,self::compensationVoluntaryNode,$translationPrefix.self::compensationVoluntaryNode);
        // further description
        $this->addFormElement($builder,self::compensationTextNode,'textarea');
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $tempArray = $viewData[self::compensationTypeNode];
        $this->setSelectedCheckboxes($forms,$tempArray);
        if ($tempArray!=='' && !array_key_exists(self::compensationNo,$tempArray)) {
            // types
            foreach ($tempArray as $selection => $value) {
                $isMoney = $selection===self::compensationMoney;
                if ($isMoney || $selection===self::compensationHours) {
                    $this->setSpinner($forms,$value,$selection.self::valueSuffix,$isMoney ? self::descriptionNode : self::hourAdditionalNode2);
                    $forms[$selection.self::amountSuffix]->setData($value[self::moneyHourAdditionalNode]);
                    if ($isMoney) {
                        $moneyFurtherArray = $value[self::moneyFurther];
                        $forms[self::moneyFurther]->setData($moneyFurtherArray[self::chosen]);
                        $forms[self::moneyFurther.self::descriptionCap]->setData($this->getArrayValue($moneyFurtherArray,self::descriptionNode));
                    }
                }
                if (!$isMoney) {
                    $forms[$selection.self::descriptionCap]->setData($value[self::descriptionNode]);
                }
                // awarding
                $isNotCompensationOther = $selection!==self::compensationOther;
                $awarding = $value[self::awardingNode];
                $chosen = $awarding[self::chosen];
                $forms[$isNotCompensationOther ? self::awardingNode.$selection : self::awardingOtherDescription]->setData($chosen);
                if ($selection===self::compensationLottery) { // when and how the result is communicated
                    $tempVal = self::lotteryStart.self::descriptionCap;
                    $forms[$tempVal]->setData($awarding[$tempVal]);
                    $forms[self::lotteryStart]->setData($awarding[self::lotteryStart]);
                    $forms[self::lotteryStartOtherDescription]->setData($this->getArrayValue($awarding,self::lotteryStartOtherDescription));
                }
                $description = $selection.$chosen.self::descriptionCap;
                if ($chosen!=='' && array_key_exists($description,$forms)) { // if an empty string, $description could be equivalent to the description field of the selection
                    $forms[$description]->setData($this->getArrayValue($awarding,self::descriptionNode));
                }
                if ($isNotCompensationOther && $chosen===self::awardingLater) {
                    $forms[$selection.self::laterTypesName]->setData($awarding[self::laterTypesName]); // information that is needed
                    $forms[$selection.self::laterOtherDescription]->setData($this->getArrayValue($awarding,self::laterOtherDescription));
                }
            }
            // terminate
            $tempArray = $viewData[self::terminateNode];
            $forms[self::terminateNode]->setData($tempArray[self::chosen]);
            $forms[self::terminateNode.self::descriptionCap]->setData($this->getArrayValue($tempArray,self::descriptionNode));
            // compensation Voluntary
            $forms[self::compensationVoluntaryNode]->setData($this->getArrayValue($viewData,self::compensationVoluntaryNode));
            // further description
            $forms[self::compensationTextNode]->setData($viewData[self::compensationTextNode]);
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $tempArray = $this->getSelectedCheckboxes($forms,self::compensationTypes,exclusive: self::compensationNo);
        $newData = [self::compensationTypeNode => $tempArray];
        if ($tempArray!==[] && !array_key_exists(self::compensationNo,$tempArray)) {
            // types
            foreach ($tempArray as $selection => $value) {
                $isMoney = $selection===self::compensationMoney;
                $isHours = $selection===self::compensationHours;
                $description = $forms[$selection.($isMoney ? self::valueSuffix : self::descriptionCap)]->getData();
                if ($isMoney) {
                    $description = $this->checkMinMax($description);
                }
                $tempData = [self::descriptionNode => $description];
                if ($isMoney || $isHours) {
                    $amount = $forms[$selection.self::amountSuffix]->getData();
                    $tempData[self::moneyHourAdditionalNode] = $amount;
                    if ($isMoney) { // question if value can change during the study
                        $tempVal = $forms[self::moneyFurther]->getData();
                        $moneyFurtherArray = [self::chosen => $tempVal];
                        if ($tempVal===0) {
                            $moneyFurtherArray[self::descriptionNode] = $forms[self::moneyFurther.self::descriptionCap]->getData();
                        }
                        $tempData[self::moneyFurther] = $moneyFurtherArray;
                    }
                    if ($isHours && $amount===self::amountFlat) {
                        $tempData[self::hourAdditionalNode2] = $this->checkMinMax($forms[self::compensationHours.self::valueSuffix]->getData());
                    }
                }
                // awarding
                $isNotCompensationOther = $selection!==self::compensationOther;
                $chosen = $forms[$isNotCompensationOther ? self::awardingNode.$selection : self::awardingOtherDescription]->getData();
                $awardingData = [self::chosen => $chosen];
                if ($selection===self::compensationLottery) { // when and how the result is communicated
                    $tempVal = self::lotteryStart.self::descriptionCap;
                    $awardingData[$tempVal] = $forms[$tempVal]->getData();
                    $tempVal = $forms[self::lotteryStart]->getData();
                    $awardingData[self::lotteryStart] = $tempVal;
                    if ($tempVal===self::lotteryResultOther) {
                        $awardingData[self::lotteryStartOtherDescription] = $forms[self::lotteryStartOtherDescription]->getData();
                    }
                }
                if ($isNotCompensationOther && $chosen!==null) {
                    $description = $selection.$chosen.self::descriptionCap;
                    if (array_key_exists($description,$forms)) {
                        $awardingData[self::descriptionNode] = $forms[$description]->getData();
                    }
                    if ($chosen===self::awardingLater) {
                        $laterChosen = $forms[$selection.self::laterTypesName]->getData(); // information that is needed
                        $awardingData[self::laterTypesName] = $laterChosen;
                        if ($laterChosen===self::laterEndOther) { // other information is needed
                            $awardingData[self::laterOtherDescription] = $forms[$selection.self::laterOtherDescription]->getData();
                        }
                    }
                }
                $tempData[self::awardingNode] = $awardingData;
                $tempArray[$selection] = $tempData;
            }
            $newData[self::compensationTypeNode] = $tempArray;
            // terminate
            $tempVal = $forms[self::terminateNode]->getData();
            $tempArray = [self::chosen => $tempVal];
            if (in_array($tempVal,self::terminateTypesDescription)) {
                $tempArray[self::descriptionNode] = $forms[self::terminateNode.self::descriptionCap]->getData();
            }
            $newData[self::terminateNode] = $tempArray;
            // compensation voluntary
            $newData[self::compensationVoluntaryNode] = $forms[self::compensationVoluntaryNode]->getData();
            // further description
            $newData[self::compensationTextNode] = $forms[self::compensationTextNode]->getData();
        }
        $viewData = $newData;
    }

    /** Creates an array with key 'attr' whose value is an array with key 'placeholder' and text as value.
     * @param string $text value for inner array
     * @return array placeholder array
     */
    private function getPlaceholder(string $text): array {
        return ['attr' => ['placeholder' => $text]];
    }

    /** Checks if the given value is smaller than self::valueMin or greater than self::valueMax. If so, it is set to self::$valueMin or self::valueMax, respectively. If \$value is an empty string or not a number, an empty string is returned.
     * @param float|string|null $value value to be checked
     * @return float|string $value, eventually updated
     */
    private function checkMinMax(float|string|null $value): float|string {
        $isCommaSeparator = false;
        if (gettype($value)==='string') {
            $isCommaSeparator = str_contains($value,',');
            $value = str_replace(',','.',$value);
        }
        $value = (float) $value;
        if ($value===0.0) {
            $value = '';
        }
        else {
            $isSmaller = $value<self::valueMin;
            if ($isSmaller || $value>self::valueMax) {
                $value = $isSmaller ? self::valueMin : self::valueMax;
            }
            $value = round($value,2);
        }
        if ($isCommaSeparator) {
            $value = str_replace('.',',',(string) $value);
        }
        return $value;
    }
}