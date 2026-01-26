<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class CompensationType extends TypeAbstract
{
    use ProjectdetailsTrait;

    private bool $isDuration; // true if total duration is greater than 30 minutes

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translationPrefix = 'projectdetails.pages.compensation.';
        $typesPrefix = $translationPrefix.'types.';
        $textHintPrefix = $typesPrefix.self::textHintPlural.'.';
        $awardingPrefix = $translationPrefix.self::awardingNode.'.';
        $dummyParams = $options[self::dummyParams];
        $this->isDuration = $dummyParams['isDuration'];
        // types
        $this->addCheckboxGroup($builder,self::compensationTypes,$typesPrefix);
        $this->addFormElement($builder,self::compensationOther.self::descriptionCap,'text',hint: $textHintPrefix.self::compensationOther);
        foreach (array_diff(self::compensationTypes, [self::compensationNo]) as $type) {
            if ($type!==self::compensationMoney) {
                $this->addFormElement($builder, $type.self::descriptionCap, 'text', hint: $textHintPrefix.$type);
            }
            // awarding
            if ($type!==self::compensationOther) {
                $this->addRadioGroup($builder, $type.self::awardingNode, self::awardingTypes[$type],textName: $type.'otherDescription');
                $this->addFormElement($builder, $type.self::awardingLater.self::descriptionCap, 'text'); // later description
                $this->addRadioGroup($builder, $type.self::laterTypesName, self::laterTypes,textName: $type.self::laterOtherDescription, options: $this->getPlaceholder($awardingPrefix.'laterEnd.placeholder')); // later types
            }
            if (in_array($type, [self::compensationMoney, self::compensationLottery])) { // external description
                $this->addFormElement($builder, $type.'externalDescription', 'text');
            }
        }
        // further widgets if money is selected
        $this->addBinaryRadio($builder, self::moneyFurther, $translationPrefix.self::compensationMoney.'.title', self::moneyFurther.self::descriptionCap);
        // further awarding widgets
        $tempPrefix = $awardingPrefix.self::compensationLottery.'.result.';
        $this->addFormElement($builder, self::lotteryStart.self::descriptionCap, 'text', hint: $tempPrefix.self::textHint); // description of lottery start in awarding
        $this->addRadioGroup($builder, self::lotteryStart, self::lotteryTypes,textName: self::lotteryStartOtherDescription, options: $this->getPlaceholder($tempPrefix.'types.placeholder')); // start of lottery
        $this->addRadioGroup($builder, 'lotterydeliver'.self::descriptionCap, self::lotteryDeliverTypes); // deliver types for lottery
        $this->addRadioGroup($builder, 'voucherdeliver'.self::descriptionCap, self::voucherDeliverTypes, textName: self::awardingOtherDescription, textHint: $awardingPrefix.self::compensationOther.'.'.self::textHint); // deliver types for voucher and description of other type
        foreach ([self::compensationMoney, self::compensationHours] as $type) {
            $this->addFormElement($builder, $type.self::valueSuffix, $type===self::compensationMoney ? 'money' : 'text', $typesPrefix.$type.'EndDefault');
            $this->addRadioGroup($builder, $type.self::amountSuffix, self::valueTypes);
        }
        // terminate
        $tempPrefix = $translationPrefix.self::terminateNode.'.';
        $this->addRadioGroup($builder,self::terminateNode,$this->translateArray($tempPrefix.'types.',array_merge(self::terminateTypes,[self::terminateNothing,self::terminateOther])),$tempPrefix.'title',self::terminateNode.self::descriptionCap);
        // compensation voluntary
        $tempPrefix = $translationPrefix.self::compensationVoluntaryNode.'.';
        $this->addBinaryRadio($builder,self::compensationVoluntaryNode,$tempPrefix.'title',self::compensationVoluntaryNode.self::descriptionCap,$tempPrefix.self::textHint);
        // further description
        $this->addFormElement($builder,self::compensationTextNode,'textarea',hint: $translationPrefix.self::compensationTextNode.'.'.self::textHint);
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        $tempArray = $viewData[self::compensationTypeNode];
        $this->setSelectedCheckboxes($forms,$tempArray);
        if ($tempArray!=='' && !array_key_exists(self::compensationNo,$tempArray)) {
            // types
            $hasDescription = array_key_exists(self::compensationMoney.self::valueSuffix,$forms);
            $hasAwarding = array_key_exists(self::compensationMoney.self::awardingNode,$forms);
            foreach ($tempArray as $selection => $value) {
                $isMoney = $selection===self::compensationMoney;
                $description = $selection.self::descriptionCap;
                if ($hasDescription) {
                    $descriptionArray = $viewData[$description];
                    if ($isMoney || $selection===self::compensationHours) {
                        $this->setSpinner($forms,$descriptionArray,$selection.self::valueSuffix,$isMoney ? self::descriptionNode : self::hourAdditionalNode2);
                        $forms[$selection.self::amountSuffix]->setData($descriptionArray[self::moneyHourAdditionalNode]);
                        if ($isMoney) {
                            $this->setChosenArray($forms,$viewData,self::moneyFurther,[self::descriptionNode => self::moneyFurther.self::descriptionCap]);
                        }
                    }
                    if (!$isMoney) {
                        $forms[$description]->setData($descriptionArray[self::descriptionNode]);
                    }
                    // awarding
                    $isNotCompensationOther = $selection!==self::compensationOther;
                    if ($hasAwarding) {
                        $tempVal = $selection.self::awardingNode;
                        $awarding = $viewData[$tempVal];
                        $chosen = $awarding[self::chosen];
                        if ($isNotCompensationOther) {
                            $description = self::lotteryStart.self::descriptionCap;
                            $this->setChosenArray($forms,$viewData,$tempVal,array_merge([self::laterTypesName => $selection.self::laterTypesName,self::laterOtherDescription => $selection.self::laterOtherDescription],$selection===self::compensationLottery ? [$description => $description,self::lotteryStart => self::lotteryStart,self::lotteryStartOtherDescription => self::lotteryStartOtherDescription] : [])); // lottery: when and how the result is communicated
                        } else {
                            $forms[self::awardingOtherDescription]->setData($chosen);
                        }
                        $description = $selection.$chosen.self::descriptionCap;
                        if ($chosen!=='' && array_key_exists($description,$forms)) { // if an empty string, $description could be equivalent to the description field of the selection
                            $forms[$description]->setData($this->getArrayValue($awarding,self::descriptionNode));
                        }
                    } // hasAwarding
                } // hasDescription
            }
            // terminate
            $this->setChosenArray($forms,$viewData,self::terminateNode,[self::descriptionNode => self::terminateNode.self::descriptionCap]);
            // compensation Voluntary
            $this->setChosenArray($forms,$viewData,self::compensationVoluntaryNode,[self::descriptionNode => self::compensationVoluntaryNode.self::descriptionCap]);
            // further description
            if (array_key_exists(self::compensationTextNode,$forms)) {
                $forms[self::compensationTextNode]->setData($viewData[self::compensationTextNode]);
            }
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        $tempArray = $this->getSelectedCheckboxes($forms,self::compensationTypes,exclusive: self::compensationNo);
        $newData = [self::compensationTypeNode => $tempArray];
        if ($tempArray!==[] && !array_key_exists(self::compensationNo,$tempArray)) {
            // types
            $hasDescription = array_key_exists(self::compensationMoney.self::valueSuffix,$forms); // true if further inputs need to be made
            $hasAwarding = array_key_exists(self::compensationMoney.self::awardingNode,$forms);
            foreach ($tempArray as $selection => $value) {
                if ($hasDescription) {
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
                        if ($isHours && $amount===self::amountFlat) {
                            $tempData[self::hourAdditionalNode2] = $this->checkMinMax($forms[self::compensationHours.self::valueSuffix]->getData());
                        }
                    }
                    $newData[$selection.self::descriptionCap] = $tempData;
                    if ($isMoney) { // question if value can change during the study
                        $newData[self::moneyFurther] = $this->getChosenArray($forms,self::moneyFurther,0,[self::descriptionNode => self::moneyFurther.self::descriptionCap]);
                    }
                    // awarding
                    $awarding = $selection.self::awardingNode;
                    if ($hasAwarding) {
                        $isNotCompensationOther = $selection!==self::compensationOther;
                        $chosen = $forms[$isNotCompensationOther ? $awarding : self::awardingOtherDescription]->getData();
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
                        $newData[$awarding] = $awardingData;
                    } // hasAwarding
                } // hasDescription
            }
            // terminate
            $tempVal = $forms[self::terminateNode]->getData();
            $tempArray = [self::chosen => $tempVal];
            if ($tempVal===self::terminateNothing && $this->isDuration || $tempVal===self::terminateOther) {
                $tempArray[self::descriptionNode] = $forms[self::terminateNode.self::descriptionCap]->getData();
            }
            $newData[self::terminateNode] = $tempArray;
            // compensation voluntary
            $newData[self::compensationVoluntaryNode] = $this->getChosenArray($forms,self::compensationVoluntaryNode,0,[self::descriptionNode => self::compensationVoluntaryNode.self::descriptionCap]);
            // further description
            if (array_key_exists(self::compensationTextNode,$forms)) {
                $newData[self::compensationTextNode] = $forms[self::compensationTextNode]->getData();
            }
        }
        $viewData = $newData;
    }

    /** Checks if the given value is smaller than self::valueMin or greater than self::valueMax. If so, it is set to self::$valueMin or self::valueMax, respectively. If \$value is an empty string or not a number, an empty string is returned.
     * @param float|string|null $value value to be checked
     * @return float|string $value, eventually updated
     */
    private function checkMinMax(float|string|null $value): float|string
    {
        $isCommaSeparator = false;
        if (gettype($value)==='string') {
            $isCommaSeparator = str_contains($value,',');
            $value = str_replace(',','.',$value);
        }
        $value = (float) $value;
        if ($value===0.0) {
            $value = '';
        } else {
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