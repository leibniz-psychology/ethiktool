<?php

namespace App\Form\Projectdetails;

use App\Abstract\TypeAbstract;
use App\Traits\Projectdetails\ProjectdetailsTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class DataPrivacyType extends TypeAbstract
{
    use ProjectdetailsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'projectdetails.pages.dataPrivacy.';
        $dummyParams = $options[self::dummyParams];
        // processing
        $tempPrefix = $translationPrefix.self::processingNode.'.';
        $this->addFormElement($builder,self::processingNode,'textarea',$tempPrefix.'title',hint: $tempPrefix.self::textHint);
        // create
        $tempPrefix = $translationPrefix.self::createNode.'.';
        $this->addRadioGroup($builder,self::createNode,self::createTypes,$tempPrefix.'title');
        $this->addRadioGroup($builder,self::createVerificationNode,self::verificationTypes,$tempPrefix.self::createVerificationNode.'.title');
        // confirm
        $this->addFormElement($builder,self::confirmIntroNode,'checkbox',$translationPrefix.self::introNode.'.confirm');
        // responsibility
        $this->addRadioGroup($builder,self::responsibilityNode,array_diff(self::responsibilityTypes,$options[self::committeeParams][self::committeeType]!==self::committeeEUB ? self::responsibilityPrivate : []),$translationPrefix.self::responsibilityNode.'.title');
        // transfer outside
        $this->addRadioGroup($builder,self::transferOutsideNode,self::transferOutsideTypes,$translationPrefix.self::transferOutsideNode.'.title');
        if ($dummyParams['isOnline']) { // only if location is online
            // data online
            $this->addRadioGroup($builder,self::dataOnlineNode,self::dataOnlineTypes,$translationPrefix.self::dataOnlineNode.'.title');
            // data online processing
            $this->addRadioGroup($builder,self::dataOnlineProcessingNode,self::dataOnlineProcessingTypes,$translationPrefix.self::dataOnlineProcessingNode.'.title');
        }
        // data personal
        $this->addRadioGroup($builder,self::dataPersonalNode,self::dataPersonalTypes,$translationPrefix.self::dataPersonalNode.'.title');
        // marking
        $tempPrefix = $translationPrefix.self::markingNode.'.';
        foreach (['',self::markingSuffix] as $suffix) {
            $marking = self::markingNode.$suffix;
            $this->addRadioGroup($builder,$marking,$suffix==='' ? self::markingTypes : array_flip(array_diff_key(array_flip(self::markingTypes),[self::markingNo => '', self::markingOther => '']))); // text field is for external and name
            $this->addFormElement($builder,$marking.self::descriptionCap,'text'); // text field is for external and name
            $this->addRadioGroup($builder,self::markingInternal.$suffix,self::internalTypes,$tempPrefix.self::markingInternal.'.title'); // how the code is created
            $title = $tempPrefix.'code';
            foreach (['external','pattern','own','contributors'] as $key) { // whether the code has personal data
                $this->addRadioGroup($builder,$key.$suffix,self::markingSubTypes[$key],$title);
            }
        }
        // marking further
        $this->addBinaryRadio($builder,self::markingFurtherNode,$translationPrefix.self::markingFurtherNode);
        // list
        $tempPrefix = $translationPrefix.self::listNode.'.types.';
        $this->addCheckboxGroup($builder,self::listTypes,$tempPrefix,[$this->appendText(self::listOther)],[$tempPrefix.'placeholder']);
        // data research
        $tempPrefix = $translationPrefix.self::dataResearchNode.'.';
        $otherPrefix = $tempPrefix.'hints.';
        $this->addCheckboxGroup($builder,self::dataResearchTypesAll,$tempPrefix.'types.',array_values($this->createPrefixArray(self::dataResearchTextFieldsAll)),array_merge(array_fill(0,count(self::dataResearchTextFieldsAll)-1,$otherPrefix.self::descriptionNode),[$otherPrefix.self::dataResearchSpecialOther]));
        // anonymization
        $tempPrefix = $translationPrefix.self::anonymizationNode.'.types.';
        $this->addCheckboxGroup($builder,self::anonymizationTypes,$tempPrefix,$this->appendText(self::anonymizationOther),[$tempPrefix.'placeholder']);
        // storage
        $tempPrefix = $translationPrefix.self::storageNode.'.';
        $this->addRadioGroup($builder,self::storageNode,self::storageTypes,$tempPrefix.'title');
        $this->addFormElement($builder,self::storageNode.self::descriptionCap,'text',hint: $tempPrefix.self::textHint);
        // personal keep
        $tempPrefix = $translationPrefix.self::personalKeepNode.'.';
        $hint = $tempPrefix.'hints.'.self::textHint;
        $this->addCheckboxGroup($builder,self::personalKeepTypes,$tempPrefix.'types.');
        $consentTitle = $translationPrefix.self::personalKeepConsentNode.'.title';
        $consentKeys = array_keys(self::personalKeepConsentTypes);
        $consentValues = array_values(self::personalKeepConsentTypes);
        foreach (self::personalKeepTypes as $type) {
            $this->addFormElement($builder,$this->appendText($type),'text',hint: $hint);
            // personal keep consent
            $this->addRadioGroup($builder,$type.self::personalKeepConsentNode,array_combine($consentKeys,$this->prefixArray($consentValues,$type)),$consentTitle,options: [self::labelParams => ['type' => $this->translateString($tempPrefix.'typesShort.'.$type)]]);
        }
        // purpose research
        $this->addCheckboxGroup($builder,self::purposeResearchTypes,$translationPrefix.self::purposeResearchNode.'.types.');
        // purpose further
        $this->addCheckboxGroup($builder,$this->prefixArray(self::purposeFurtherTypes,self::purposeFurtherNode),$translationPrefix.self::purposeFurtherNode.'.types.',labelNames: self::purposeFurtherTypes);
        $purposePrefix = $translationPrefix.self::purposeDataNode.'.';
        $placeholder = $purposePrefix.'placeholder';
        $accessPlaceholder = $translationPrefix.self::accessNode.'.hints.placeholder';
        $accessPlaceholderArray = [$accessPlaceholder,$accessPlaceholder,$accessPlaceholder];
        $accessTypesPrefix = $translationPrefix.self::accessNode.'.types.';
        foreach (array_merge(self::allPurposeTypes,[self::dataPersonalNode]) as $type) {
            if ($type!==self::dataPersonalNode) {
                // purpose data
                if ($type!==self::purposeTechnical) { // types of personal data only for the other purposes
                    $this->addCheckboxGroup($builder,$this->prefixArray(self::purposeDataTypes,$type),$purposePrefix.'types.',[$this->appendText($type.self::purposeDataOther)],[$placeholder],labelNames: self::purposeDataTypes);
                }
                // marking remove
                if ($type!=='contactResult') {
                    $tempPrefix = $translationPrefix.self::markingRemoveNode.'.';
                    $this->addRadioGroup($builder, $type.self::markingRemoveNode, array_combine(array_keys(self::markingRemoveTypes), $this->prefixArray(array_values(self::markingRemoveTypes), $type)), textareaName: $type.self::laterDescription, textHint: $tempPrefix.self::textHintPlural.'.'.self::laterDescription);
                    $this->addFormElement($builder, $type.self::markingRemoveNode.self::descriptionCap, 'text');
                    $this->addCheckboxGroup($builder, $this->prefixArray(self::markingRemoveMiddleTypes, $type), $tempPrefix.self::markingRemoveMiddleNode.'.types.', labelNames: self::markingRemoveMiddleTypes);
                }
                // personal remove
                $tempPrefix = $translationPrefix.self::personalRemoveNode.'.textHints.';
                $this->addRadioGroup($builder,$type.self::personalRemoveNode,array_combine(array_keys(self::personalRemoveTypes),$this->prefixArray(array_values(self::personalRemoveTypes),$type)),textareaName: $type.self::keepDescription,textHint: $tempPrefix.self::personalRemoveKeep);
                $this->addFormElement($builder,$type.self::personalRemoveNode.self::descriptionCap,'text',hint: $tempPrefix.self::personalRemoveImmediately);
            }
            // access
            $this->addCheckboxGroup($builder,$this->prefixArray(self::accessTypes,$type),$accessTypesPrefix,array_values($this->createPrefixArray(self::accessOthers,$type)),$accessPlaceholderArray,labelNames: self::accessTypes);
            foreach (self::accessOrderProcessing as $accessType) {
                $prefix = $type.$accessType;
                // order processing
                $orderProcessing = $prefix.self::orderProcessingNode;
                $tempPrefix = $translationPrefix.self::orderProcessingNode.'.';
                $this->addBinaryRadio($builder,$orderProcessing,$tempPrefix.'title',options: [self::labelParams => ['type' => $accessType]]);
                if ($accessType===self::accessContributorsOther) {
                    $this->addFormElement($builder,$orderProcessing.self::descriptionCap,'text',hint: $tempPrefix.self::descriptionNode);
                }
                // order processing known
                $this->addRadioGroup($builder,$prefix.self::orderProcessingKnownNode,self::orderProcessingKnownTypes,$translationPrefix.self::orderProcessingKnownNode.'.title');
            }
        }
        // order processing description
        $tempPrefix = $translationPrefix.self::orderProcessingDescriptionNode.'.';
        foreach (self::orderProcessingKnownTexts as $text) {
            $this->addFormElement($builder,$text,'textarea',hint: $this->translateString($tempPrefix.'text.'.$text).($text!==self::orderProcessingNode.'Start' ? '' : ' ... ').' ('.$this->translateString($tempPrefix.'hints.'.$text).')');
        }
        // relatable
        $this->addCheckboxGroup($builder,self::relatableTypes,$translationPrefix.self::relatableNode.'.types.');
        // compensation code
        if ($dummyParams['isCompensationCode']) {
            $tempPrefix = $translationPrefix.self::codeCompensationNode.'.';
            $this->addRadioGroup($builder,self::codeCompensationNode,self::codeCompensationTypes,$tempPrefix.'title');
            $this->addFormElement($builder,self::codeCompensationNode.self::descriptionCap,'text',hint: $tempPrefix.'hints.textHintCodeExternal');
            $this->addRadioGroup($builder,self::codeCompensationInternal,self::codeCompensationInternalTypes,$tempPrefix.self::codeCompensationInternal.'.title'); // how the code is created
            $tempPrefix .= 'code';
            foreach (self::codeCompensationKeys as $key) { // whether the code has personal data
                $this->addRadioGroup($builder,self::codeCompensationNode.$key,self::codeCompensationSubTypes[$key],$tempPrefix);
            }
        }
        // processing further
        $this->addFormElement($builder,self::processingFurtherNode,'textarea',hint: $translationPrefix.self::processingFurtherNode.'.'.self::textHint);
        // dummy forms
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        $forms[self::processingNode]->setData($viewData[self::processingNode] ?? ''); // processing
        $tempArray = $viewData[self::createNode];
        $create = $tempArray[self::chosen];
        $forms[self::createNode]->setData($create);
        $description = $this->getArrayValue($tempArray,self::descriptionNode); // either confirm intro or verification of separate pdf, if any of these two was chosen
        $isTool = $create===self::createTool;
        $forms[self::createVerificationNode]->setData(!$isTool ? $description : '');
        if ($isTool) {
            $tempVal = $description==='1';
            $forms[self::confirmIntroNode]->setData($tempVal); // confirm
            if ($tempVal) {
                $responsibility = $viewData[self::responsibilityNode]; // responsibility
                $transferOutside = $viewData[self::transferOutsideNode]; // transfer outside
                $forms[self::responsibilityNode]->setData($responsibility);
                $forms[self::transferOutsideNode]->setData($transferOutside);
                if (in_array($responsibility,[self::responsibilityOnlyOwn,self::privacyNotApplicable]) && in_array($transferOutside,[self::transferOutsideNo,self::privacyNotApplicable])) {
                    // data online
                    if (array_key_exists(self::dataOnlineNode,$viewData)) {
                        $forms[self::dataOnlineNode]->setData($viewData[self::dataOnlineNode]);
                    }
                    // data online processing
                    if (array_key_exists(self::dataOnlineProcessingNode,$viewData)) {
                        $forms[self::dataOnlineProcessingNode]->setData($viewData[self::dataOnlineProcessingNode]);
                    }
                    // data personal
                    $forms[self::dataPersonalNode]->setData($viewData[self::dataPersonalNode]);
                    // marking
                    foreach (array_merge([''], array_key_exists(self::markingNode.self::markingSuffix, $viewData) ? [self::markingSuffix] : []) as $suffix) {
                        $marking = self::markingNode.$suffix;
                        $tempArray = $viewData[$marking];
                        $tempVal = $tempArray[self::chosen];
                        $forms[$marking]->setData($tempVal);
                        $forms[$marking.self::descriptionCap]->setData($this->getArrayValue($tempArray, self::descriptionNode));
                        if ($tempVal!=='') {
                            $internalVal = $this->getArrayValue($tempArray, self::markingInternal);
                            $forms[self::markingInternal.$suffix]->setData($internalVal);
                            $isExternal = $tempVal===self::markingExternal;
                            if ($isExternal || $tempVal===self::markingInternal && $internalVal!=='') {
                                $forms[($isExternal ? self::markingExternal : $internalVal).$suffix]->setData($this->getArrayValue($tempArray, self::codePersonal));
                            }
                        }
                    }
                    // marking further
                    $forms[self::markingFurtherNode]->setData($this->getArrayValue($viewData,self::markingFurtherNode));
                    // list
                    if (array_key_exists(self::listNode, $viewData)) {
                        $this->setSelectedCheckboxes($forms, $viewData[self::listNode], [self::listOther => $this->appendText(self::listOther)]);
                    }
                    // data research
                    if (array_key_exists(self::dataResearchNode, $viewData)) {
                        $this->setSelectedCheckboxes($forms, $viewData[self::dataResearchNode], array_combine(self::dataResearchTextFieldsAll, $this->createPrefixArray(self::dataResearchTextFieldsAll)));
                    }
                    // anonymization
                    if (array_key_exists(self::anonymizationNode, $viewData)) {
                        $this->setSelectedCheckboxes($forms,$viewData[self::anonymizationNode],[self::anonymizationOther => $this->appendText(self::anonymizationOther)]);
                    }
                    // storage
                    if (array_key_exists(self::storageNode,$viewData)) {
                        $tempArray = $viewData[self::storageNode];
                        $forms[self::storageNode]->setData($tempArray[self::chosen]);
                        $forms[self::storageNode.self::descriptionCap]->setData($this->getArrayValue($tempArray, self::descriptionNode));
                    }
                    if (array_key_exists(self::personalKeepNode, $viewData)) {
                        // personal keep
                        $personalKeep = $viewData[self::personalKeepNode];
                        $this->setSelectedCheckboxes($forms, $personalKeep, $this->createPrefixArray(self::personalKeepTypes));
                        // personal keep consent
                        if ($personalKeep!=='') {
                            foreach ($viewData[self::personalKeepConsentNode] as $type => $selection) {
                                $forms[$type.self::personalKeepConsentNode]->setData($type.$selection);
                            }
                        }
                    }
                    // access if research data is personal
                    if (array_key_exists(self::accessNode, $viewData)) {
                        $this->setAccess($forms,$viewData[self::accessNode],self::dataPersonalNode);
                    }
                    // purposeResearch
                    $isPurpose = array_key_exists(self::purposeResearchNode, $viewData);
                    if ($isPurpose) {
                        $this->setSelectedCheckboxes($forms, $viewData[self::purposeResearchNode]);
                    }
                    // purpose further
                    $isPurposeFurther = array_key_exists(self::purposeFurtherNode, $viewData);
                    if ($isPurposeFurther) {
                        $this->setSelectedCheckboxes($forms, $viewData[self::purposeFurtherNode]);
                    }
                    foreach ([self::purposeResearchNode => $isPurpose, self::purposeFurtherNode => $isPurposeFurther] as $purposeType => $hasPurpose) {
                        if ($hasPurpose) {
                            $purposeArray = $viewData[$purposeType];
                            $purposePrefix = $purposeType===self::purposeFurtherNode ? self::purposeFurtherNode : '';
                            if ($purposeArray!=='' && !array_key_exists($purposePrefix.self::purposeNo,$purposeArray)) {
                                foreach ($purposeArray as $purpose => $questions) { // keys: selected purposes, values: sub-questions for each purpose
                                    $purposeWoPrefix = str_replace(self::purposeFurtherNode, '', $purpose);
                                    if ($questions!=='') {
                                        // purpose data
                                        if ($purposeWoPrefix!==self::purposeTechnical) {
                                            $other = $purposeWoPrefix.self::purposeDataOther;
                                            $this->setSelectedCheckboxes($forms, $questions[self::purposeDataNode], [$other => $this->appendText($other)]);
                                        }
                                        // marking remove
                                        if (array_key_exists(self::markingRemoveNode, $questions)) {
                                            $markingRemove = $purpose.self::markingRemoveNode;
                                            $tempArray = $questions[self::markingRemoveNode];
                                            $forms[$markingRemove]->setData($tempArray[self::chosen]);
                                            $forms[$markingRemove.self::descriptionCap]->setData($this->getArrayValue($tempArray, self::descriptionNode));
                                            $forms[$purpose.self::laterDescription]->setData($this->getArrayValue($tempArray, self::laterDescription));
                                            // middle
                                            if (array_key_exists(self::markingRemoveMiddleNode, $tempArray)) {
                                                $this->setSelectedCheckboxes($forms, $tempArray[self::markingRemoveMiddleNode]);
                                            }
                                        }
                                        // personal remove
                                        $personalRemove = $purposeWoPrefix.self::personalRemoveNode;
                                        $tempArray = $questions[self::personalRemoveNode];
                                        $tempVal = $tempArray[self::chosen];
                                        $forms[$personalRemove]->setData($tempVal);
                                        if (array_key_exists(self::descriptionNode, $tempArray)) {
                                            $forms[$tempVal===$purposeWoPrefix.self::personalRemoveImmediately ? $personalRemove.self::descriptionCap : $purposeWoPrefix.self::keepDescription]->setData($tempArray[self::descriptionNode]);
                                        }
                                        // access
                                        $this->setAccess($forms,$questions[self::accessNode],$purposeWoPrefix);
                                    } // if (questions!=='')
                                } // foreach purpose
                            } // if any purpose except 'no purpose'
                        } // if ($hasPurpose)
                    } // foreach purpose/purpose further
                    // relatable
                    if (array_key_exists(self::relatableNode,$viewData)) {
                        $this->setSelectedCheckboxes($forms,$viewData[self::relatableNode]);
                    }
                    // order processing description
                    if (array_key_exists(self::orderProcessingDescriptionNode,$viewData)) {
                        foreach ($viewData[self::orderProcessingDescriptionNode] as $description => $value) {
                            $forms[$description]->setData($value);
                        }
                    }
                    // code compensation
                    if (array_key_exists(self::codeCompensationNode, $viewData)) {
                        $tempArray = $viewData[self::codeCompensationNode];
                        $tempVal = $tempArray[self::chosen];
                        $forms[self::codeCompensationNode]->setData($tempVal);
                        $forms[self::codeCompensationNode.self::descriptionCap]->setData($this->getArrayValue($tempArray, self::descriptionNode));
                        if ($tempVal!=='') {
                            $internalVal = $this->getArrayValue($tempArray, self::codeCompensationInternal);
                            $forms[self::codeCompensationInternal]->setData($internalVal);
                            $isExternal = $tempVal===self::codeCompensationExternal;
                            if ($isExternal || $tempVal===self::codeCompensationInternal && in_array($internalVal,self::codeCompensationKeys)) {
                                $forms[self::codeCompensationNode.($isExternal ? 'external' : $internalVal)]->setData($this->getArrayValue($tempArray, self::codeCompensationPersonal));
                            }
                        }
                    }
                    // processing further
                    $forms[self::processingFurtherNode]->setData($this->getArrayValue($viewData,self::processingFurtherNode));
                }
            }
        }
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        $newData = [self::processingNode => $forms[self::processingNode]->getData()];
        $create = $forms[self::createNode]->getData();
        $tempArray = [self::chosen => $create];
        $isTool = $create===self::createTool;
        if ($isTool || $create===self::createSeparate) { // either confirm intro or verification of separate pdf
            $tempVal = $forms[$isTool ? self::confirmIntroNode : self::createVerificationNode]->getData();
            $tempArray[self::descriptionNode] = $tempVal;
        }
        $newData[self::createNode] = $tempArray;
        if ($isTool) {
            if ($tempVal) {
                $responsibility = $forms[self::responsibilityNode]->getData(); // responsibility
                $transferOutside = $forms[self::transferOutsideNode]->getData(); // transfer outside
                $newData[self::responsibilityNode] = $responsibility;
                $newData[self::transferOutsideNode] = $transferOutside;
                if (in_array($responsibility,[self::responsibilityOnlyOwn,self::privacyNotApplicable]) && in_array($transferOutside,[self::transferOutsideNo,self::privacyNotApplicable])) {
                    $isLinked = false;
                    // data online
                    $isResearch = false;
                    if (array_key_exists(self::dataOnlineNode,$forms)) {
                        $tempVal = $forms[self::dataOnlineNode]->getData();
                        $newData[self::dataOnlineNode] = $tempVal;
                        if ($tempVal===self::dataOnlineTechnical) {
                            $tempVal = $forms[self::dataOnlineProcessingNode]->getData();
                            $newData[self::dataOnlineProcessingNode] = $tempVal;
                            $isLinked = $tempVal===self::dataOnlineProcessingLinked;
                            $isResearch = $tempVal===self::dataOnlineProcessingResearch;
                        }
                    }
                    // data personal
                    $tempVal = $forms[self::dataPersonalNode]->getData();
                    $newData[self::dataPersonalNode] = $tempVal;
                    $isPersonal = in_array($tempVal, self::dataPersonal);
                    $isDataResearch = $isPersonal;
                    $hasFurther =  in_array($forms[self::markingNode]->getData(),self::markingValues); // true if further question is asked
                    $further = $forms[self::markingFurtherNode]->getData(); // marking further
                    // marking
                    $isList = false;
                    $isNameListGeneration = false; // marking name or internal/external and list/generation
                    $isMarking = false; // internal, external, or name
                    foreach (array_merge([''], $hasFurther && $further===0 ? [self::markingSuffix] : []) as $suffix) {
                        $marking = self::markingNode.$suffix;
                        $tempVal = $forms[$marking]->getData();
                        $tempArray = [self::chosen => $tempVal];
                        $isExternal = $tempVal===self::markingExternal;
                        $isInternal = $tempVal===self::markingInternal;
                        $isName = $tempVal===self::nameNode;
                        $isNameListGeneration = $isNameListGeneration || $isName;
                        $isDataResearch = $isDataResearch || $isName;
                        $isMarking = $isMarking || in_array(true, [$isExternal, $isInternal, $isName]);
                        if ($isExternal || $tempVal===self::markingName) {
                            $tempArray[self::descriptionNode] = $forms[self::markingNode.$suffix.self::descriptionCap]->getData();
                        }
                        if ($isExternal || $isInternal) {
                            $codeQuestion = true;
                            if ($isInternal) {
                                $tempVal = $forms[self::markingInternal.$suffix]->getData(); // how the code is created
                                $tempArray[self::markingInternal] = $tempVal;
                                $codeQuestion = $tempVal!==null;
                            }
                            if ($codeQuestion) { // whether the code has personal data
                                $tempVal = $forms[($isExternal ? self::markingExternal : $tempVal).$suffix]->getData();
                                $isList = $isList || $tempVal===self::markingList;
                                $isNameListGeneration = $isNameListGeneration || in_array($tempVal,self::markingDataResearchTypes);
                                $isDataResearch = $isDataResearch || in_array($tempVal, self::markingDataResearchTypes);
                                $tempArray[self::codePersonal] = $tempVal;
                            }
                        }
                        $newData[$marking] = $tempArray;
                    }
                    $hasPersonal = $isNameListGeneration; // true is any personal data is collected
                    if ($hasFurther) {
                        $newData[self::markingFurtherNode] = $further;
                    }
                    // list
                    if ($isList) {
                        $newData[self::listNode] = $this->getSelectedCheckboxes($forms, self::listTypes, [self::listOther => $this->appendText(self::listOther)]);
                    }
                    if ($isDataResearch || $isLinked) {
                        // data research
                        $newData[self::dataResearchNode] = $this->getSelectedCheckboxes($forms, self::dataResearchTypesAll, array_combine(self::dataResearchTextFieldsAll, $this->createPrefixArray(self::dataResearchTextFieldsAll)));
                    }
                    $isNoAnonymization = false;
                    $anonymization = [];
                    // anonymization
                    if ($isPersonal) {
                        $anonymization = $this->getSelectedCheckboxes($forms, self::anonymizationTypes,[self::anonymizationOther => $this->appendText(self::anonymizationOther)],self::anonymizationNo);
                        $newData[self::anonymizationNode] = $anonymization;
                        $isNoAnonymization = array_key_exists(self::anonymizationNo,$anonymization);
                    }
                    // storage
                    $storage = '';
                    if ($isPersonal && !$isNoAnonymization && $anonymization!==[]) {
                        $storage = $forms[self::storageNode]->getData();
                        $tempArray = [self::chosen => $storage];
                        if ($storage===self::storageDelete) {
                            $tempArray[self::descriptionNode] = $forms[self::storageNode.self::descriptionCap]->getData();
                        }
                        $newData[self::storageNode] = $tempArray;
                    }
                    if ($storage==='keep' || $isNoAnonymization) {
                        // personal keep
                        $personalKeep = $this->getSelectedCheckboxes($forms, self::personalKeepTypes, $this->createPrefixArray(self::personalKeepTypes));
                        $newData[self::personalKeepNode] = $personalKeep;
                        // personal keep consent
                        if ($personalKeep!==[]) {
                            $tempArray = [];
                            foreach ($personalKeep as $type => $value) {
                                $tempArray[$type] = str_replace($type,'',$forms[$type.self::personalKeepConsentNode]->getData());
                            }
                            $newData[self::personalKeepConsentNode] = $tempArray;
                        }
                    }
                    // access if research data is personal
                    [$newData[self::accessNode],$anyKnown] = $this->getAccess($forms,self::dataPersonalNode);
                    // purpose research
                    [$purpose, $purposeFurther] = [[], []];
                    if ($isMarking) {
                        $purpose = $this->getSelectedCheckboxes($forms, self::purposeResearchTypes, exclusive: self::purposeNo);
                    }
                    // purpose further
                    $isPurposeFurther = $forms[self::markingNode]->getData()!=='other';
                    if ($isPurposeFurther) {
                        $purposeFurther = $this->getSelectedCheckboxes($forms, $this->prefixArray(self::purposeFurtherTypes, self::purposeFurtherNode), exclusive: 'purposeFurtherpurposeNo');
                    }
                    // further questions for each purpose -> will be added as values of the purpose keys. E.g.:
                    // [purposeResearch =>
                    //      [compensation =>
                    //          [purposeData =>
                    //              [compensationname => '']
                    //          ]
                    //          [...]
                    //      ]
                    //  ]
                    // If to any purpose a description is added (i.e., getSelectedCheckboxes() contains 'other' key(s)), they must be saved separately.
                    foreach (array_merge($isMarking ? [self::purposeResearchNode => $purpose] : [], $isPurposeFurther ? [self::purposeFurtherNode => $purposeFurther] : []) as $questionName => $question) {
                        $isPurposeFurther = $questionName===self::purposeFurtherNode;
                        $prefix = $isPurposeFurther ? self::purposeFurtherNode : '';
                        foreach ($question as $purposeType => $value) { // $value is an empty string
                            $purposeWoPrefix = str_replace(self::purposeFurtherNode, '', $purposeType);
                            if ($purposeWoPrefix!==self::purposeNo) {
                                $purposeArray = [];
                                $isTechnical = $purposeWoPrefix===self::purposeTechnical;
                                // purpose data
                                if (!$isTechnical) {
                                    $other = $purposeWoPrefix .self::purposeDataOther;
                                    $purposeArray = [self::purposeDataNode => $this->getSelectedCheckboxes($forms, $this->prefixArray(self::purposeDataTypes, $purposeWoPrefix), [$other => $this->appendText($other)])];
                                }
                                $isNotOnlyTechnical = !($isTechnical && $isResearch);
                                // marking remove
                                if ($isNotOnlyTechnical && !$isPurposeFurther && $isNameListGeneration) { // only for purpose research
                                    $markingRemove = $purposeType.self::markingRemoveNode;
                                    $tempVal = $forms[$markingRemove]->getData();
                                    $tempArray = [self::chosen => $tempVal];
                                    if ($tempVal!=='') {
                                        $tempArray[self::descriptionNode] = $forms[$markingRemove.self::descriptionCap]->getData();
                                        if ($tempVal===$purposeType.self::markingRemoveLater) { // description why marking is removed later
                                            $tempArray[self::laterDescription] = $forms[$purposeType.self::laterDescription]->getData();
                                        }
                                        else { // how the marking is removed
                                            $tempArray[self::markingRemoveMiddleNode] = $this->getSelectedCheckboxes($forms, $this->prefixArray(self::markingRemoveMiddleTypes, $purposeType));
                                        }
                                    }
                                    $purposeArray[self::markingRemoveNode] = $tempArray;
                                }
                                // personal remove
                                if ($isNotOnlyTechnical) {
                                    $personalRemove = $purposeWoPrefix.self::personalRemoveNode;
                                    $tempVal = $forms[$personalRemove]->getData();
                                    $tempArray = [self::chosen => $tempVal];
                                    $isImmediately = $tempVal===$purposeWoPrefix.self::personalRemoveImmediately;
                                    if ($isImmediately || $tempVal===$purposeWoPrefix.self::personalRemoveKeep) {
                                        $tempArray[self::descriptionNode] = $forms[$isImmediately ? $personalRemove.self::descriptionCap : $purposeWoPrefix.self::keepDescription]->getData();
                                    }
                                    $purposeArray[self::personalRemoveNode] = $tempArray;
                                    // access
                                    [$purposeArray[self::accessNode],$tempVal] = $this->getAccess($forms,$purposeWoPrefix);
                                    $anyKnown = $anyKnown || $tempVal;
                                    $question[$purposeType] = $purposeArray;
                                }
                            }
                        }
                        $newData[$questionName] = $question;
                    }
                    // relatable
                    if (array_key_exists(self::purposeRelatable,$purpose)) {
                        $newData[self::relatableNode] = $this->getSelectedCheckboxes($forms,self::relatableTypes);
                    }
                    // order processing description
                    if ($anyKnown) {
                        $tempArray = [];
                        foreach (self::orderProcessingKnownTexts as $textPart) {
                            $tempArray[$textPart] = $forms[$textPart]->getData();
                        }
                        $newData[self::orderProcessingDescriptionNode] = $tempArray;
                    }
                    // compensation code
                    if (array_key_exists(self::codeCompensationNode,$forms) && $forms[self::markingNode]->getData()!==self::markingOther && !array_key_exists(self::compensationNode,$purpose)) {
                        $tempVal = $forms[self::codeCompensationNode]->getData();
                        $tempArray = [self::chosen => $tempVal];
                        $isExternal = $tempVal===self::codeCompensationExternal;
                        $isInternal = $tempVal===self::codeCompensationInternal;
                        if ($isExternal) {
                            $tempArray[self::descriptionNode] = $forms[self::codeCompensationNode.self::descriptionCap]->getData();
                        }
                        if ($isExternal || $isInternal) {
                            $codeQuestion = true;
                            if ($isInternal) {
                                $tempVal = $forms[self::codeCompensationInternal]->getData();
                                $tempArray[self::codeCompensationInternal] = $tempVal;
                                $codeQuestion = in_array($tempVal,self::codeCompensationKeys);
                            }
                            if ($codeQuestion) {
                                $tempArray[self::codeCompensationPersonal] = $forms[self::codeCompensationNode.($isInternal ? $tempVal : 'external')]->getData();
                            }
                        }
                        $newData[self::codeCompensationNode] = $tempArray;
                    }
                    // processing further
                    if ($isPersonal || $hasPersonal || array_diff_key(array_merge($purpose,$purposeFurther),[self::purposeNo => '',self::purposeFurtherNode.self::purposeNo => ''])!==[]) {
                        $newData[self::processingFurtherNode] = $forms[self::processingFurtherNode]->getData();
                    }
                }
            }
        }
        $viewData = $newData;
    }

    /** Sets the access and order processing questions
     * @param array $forms form array where the data is set
     * @param array|string $accessArray array containing the access data
     * @param string $purposeWoPrefix purpose for which the widgets are set
     * @return void
     */
    private function setAccess(array $forms, array|string $accessArray, string $purposeWoPrefix): void {
        $this->setSelectedCheckboxes($forms, $accessArray, $this->createPrefixArray(self::accessOthers, $purposeWoPrefix));
        if ($accessArray!=='') {
            foreach ($accessArray as $type => $accessQuestions) {
                if (is_array($accessQuestions)) {
                    // order processing
                    $tempArray = $accessQuestions[self::orderProcessingNode];
                    $prefix = $type.self::orderProcessingNode;
                    $forms[$prefix]->setData($tempArray[self::chosen]);
                    $prefix .= self::descriptionCap;
                    if (array_key_exists(self::descriptionNode, $tempArray)) { // may only be true for contributorsOther
                        $forms[$prefix]->setData($tempArray[self::descriptionNode]);
                    }
                    // order processing known
                    $forms[$type.self::orderProcessingKnownNode]->setData($this->getArrayValue($accessQuestions,self::orderProcessingKnownNode));
                }
            }
        }
    }

    /** Gets the data from the access and order processing questions.
     * @param array $forms form array containing the data
     * @param string $purposeWoPrefix purpose for which the questions are checked
     * @return array 0: array containing the access and eventually the order processing questions 1: true if any order processing known question was answered with yes, false otherwise
     */
    private function getAccess(array $forms, string $purposeWoPrefix): array {
        $accessSelected = $this->getSelectedCheckboxes($forms, $this->prefixArray(self::accessTypes, $purposeWoPrefix), $this->createPrefixArray(self::accessOthers, $purposeWoPrefix));
        $accessKeys = array_keys($accessSelected);
        $anyKnown = false;
        if (array_intersect($accessKeys,$this->prefixArray(self::accessOrderProcessing,$purposeWoPrefix))!==[]) {
            foreach ($accessKeys as $accessKey) {
                if (in_array(str_replace($purposeWoPrefix,'',$accessKey),self::accessOrderProcessing)) {
                    // order processing
                    $prefix = $accessKey.self::orderProcessingNode;
                    $tempVal = $forms[$prefix]->getData();
                    $tempArray = [self::chosen => $tempVal];
                    if ($accessKey===$purposeWoPrefix.self::accessContributorsOther && $tempVal===1) {
                        $tempArray[self::descriptionNode] = $forms[$prefix.self::descriptionCap]->getData();
                    }
                    $accessArray = [self::orderProcessingNode => $tempArray];
                    if ($tempVal===0) {
                        // order processing known
                        $tempVal = $forms[$accessKey.self::orderProcessingKnownNode]->getData();
                        $accessArray[self::orderProcessingKnownNode] = $tempVal;
                        $anyKnown = $anyKnown || in_array($tempVal,self::orderProcessingYesTypes);
                    }
                    $accessSelected[$accessKey] = $accessArray;
                }
            }
        }
        return [$accessSelected,$anyKnown];
    }
}