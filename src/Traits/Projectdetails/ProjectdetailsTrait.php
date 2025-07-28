<?php

namespace App\Traits\Projectdetails;

use App\Traits\PageTrait;
use App\Traits\Contributors\ContributorsTrait;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;

/** Contains all constants that are needed for the Projektdetails page, mainly the names for the form widgets and nodes. */
trait ProjectdetailsTrait
{
    use PageTrait;
    use ContributorsTrait;

    protected const addresseeParticipants = 'participants';
    protected const addresseeString = 'addresseeString';
    protected const participantsString = 'participantsString';
    protected const addresseeChildren = 'children';
    protected const addresseeWards = 'wards';
    protected const studyID = 'studyID';
    protected const groupID = 'groupID';
    protected const measureID = 'measureID';
    protected const studyName = 'studyName';
    protected const groupName = 'groupName';
    protected const addressee = 'addressee'; // session key
    protected const participant = 'participant'; // key for translation parameter that is added in the Form Type if both addressees are part of the translation
    protected const addresseeType = 'addresseeType'; // translation key
    protected const routePrefix = '/projectdetails/study/{studyID}/group/{groupID}/measure/{measureID}/';
    protected const template = 'template'; // must equal one of the values in $templateChoices
    protected const templateText = 'text'; // must equal one of the values in $templateTypes
    protected const templateTypes = ['projectdetails.templateChoices.template' => 'template', 'projectdetails.templateChoices.text' => 'text', 'projectdetails.templateChoices.no' => 'no'];
    // groups
    protected const groupsNode = 'groups';
    protected const minAge = 'minAge';
    protected const maxAge = 'maxAge';
    protected const unlimited = 'unlimited';
    protected const examinedPeopleNode = 'examinedPeople';
    protected const healthyExaminedNode = 'healthy'; // must equal one value in $examinedPeople
    protected const physicalExaminedNode = 'physical'; // must equal one value in $examinedPeople
    protected const mentalExaminedNode = 'mental'; // must equal one value in $examinedPeople
    protected const wardsExaminedNode = 'wards'; // must equal one value in $examinedPeople
    protected const dependentExaminedNode = 'dependent'; // must equal one value in $examinedPeople
    protected const examinedTypes = ['healthy', 'physical', 'mental', 'medical', 'institutional','wards','vulnerable','dependent','otherPeople'];
    protected const peopleDescription = 'peopleDescription';
    protected const closedNode = 'closed';
    protected const closedTypesNode = 'closedTypes';
    protected const closedTypes = ['closedLecture','school','closedOther'];
    protected const closedOther = 'closedOther'; // must equal one value in $closedTypes
    protected const closedOtherText = 'closedOtherText';
    protected const criteriaNode = 'criteria';
    protected const noCriteriaNode=  'noCriteria';
    protected const criteriaIncludeNode = 'include';
    protected const criteriaExcludeNode = 'exclude';
    protected const sampleSizeNode = 'sampleSize';
    protected const sampleSizeTotalNode = 'total';
    protected const sampleSizeFurtherNode = 'furtherParticulars';
    protected const sampleSizePlanNode = 'sampleSizePlan';
    protected const recruitment = 'recruitment';
    protected const recruitmentTypesNode = 'recruitmentTypes';
    protected const recruitmentTypes = ['mailing','flyer','lecture','lectureOther','media','news','database','external','multiplier','recruitmentOther'];
    protected const recruitmentLecture = 'lecture'; // must equal one value in $recruitmentTypes
    protected const recruitmentOther = 'recruitmentOther'; // must equal one value in $recruitmentTypes
    protected const recruitmentFurther = 'recruitmentFurther';
    // information(II)
    protected const informationNode = 'information';
    protected const informationAddNode = 'additional';
    protected const informationIINode = 'informationII';
    protected const informationTypes = ['projectdetails.pages.information.type.written' => 'written', 'projectdetails.pages.information.type.writtenOral' => 'writtenOral', 'projectdetails.pages.information.type.oral' => 'oral'];
    protected const pre = 'pre'; // pre information
    // widget names for yes
    protected const preType = 'preType'; // type of pre information
    protected const preContent = 'preContent'; // extent of pre information
    protected const complete = 'complete';
    protected const preContentIncomplete = ['partial','deceit'];
    protected const deceit = 'deceit'; // value must equal one value in $preContentIncomplete
    protected const preComplete = 'preComplete'; // complete information afterward
    protected const preCompleteType = 'preCompleteType'; // node name for type of complete information afterward
    protected const preCompleteText = 'preCompleteText';
    // widget names for no
    protected const preText = 'preText';
    protected const post = 'post';
    protected const postType = 'postType';
    protected const postText = 'postText';
    // other widgets
    protected const attendanceNode = 'attendance';
    // informationIII
    protected const informationIIINode = 'informationIII';
    protected const informationIIIInputsTypes = ['goals' => 'checkDoc.projectdetails.pages.informationIII.goals', 'infoBefore' => 'checkDoc.projectdetails.pages.informationIII.infoBefore', 'infoAfter' => 'checkDoc.projectdetails.pages.informationIII.infoAfter', 'explain' => 'checkDoc.projectdetails.pages.informationIII.explain'];
    // measures
    protected const measuresNode = 'measures'; // used for page and for question on page
    protected const measuresTypesNode = 'measuresType';
    protected const measuresVideo = 'measuresVideo'; // must equal one key in measureTypes
    protected const measuresTypes = ['measuresObservation','measuresVideo','measuresSurvey','measuresInstrumental','otherMeasures'];
    protected const measuresPDF = 'measuresPDF';
    protected const locationNode = 'location';
    protected const locationOnline = 'online';
    protected const interventionsNode = 'interventions';
    protected const interventionsTypesNode = 'interventionsType';
    protected const noIntervention = 'noIntervention'; // must equal one key in interventionsTypes
    protected const interventionsSurvey = 'interventionsSurvey'; // must equal one key in interventionsTypes
    protected const interventionsTypes = ['noIntervention','interventionsSurvey','feedback','everyday','stimulus','tasks','stimulation','psychological','physical','therapy','medical','invasive','other'];
    protected const interventionsPDF = 'interventionsPDF';
    protected const otherSourcesNode = 'otherSources';
    protected const otherSourcesPDF = 'otherSourcesPDF';
    protected const loanNode = 'loan';
    protected const loanReceipt = 'receipt';
    protected const presenceNode = 'presence';
    protected const durationNode = 'duration';
    protected const durationTypes = ['measureTime', 'breaks']; // must equal the values of the following two variables
    protected const durationMeasureTime = 'measureTime';
    protected const durationBreaks = 'breaks';
    // burdens/risks
    protected const burdensRisksNode = 'burdensRisks';
    protected const burdensNode = 'burdens';
    protected const burdensTypesNode = 'burdensType';
    protected const noBurdens = 'noBurdens'; // must equal one of the values in burdensTypes
    protected const burdensTypes = ['noBurdens','physical', 'mental', 'emotional', 'sensitive', 'otherBurdens'];
    protected const risksNode = 'risks';
    protected const risksTypesNode = 'risksType';
    protected const noRisks = 'noRisks'; // must equal one of the values in risksTypes
    protected const risksIntegrity = 'risksIntegrity'; // must equal one of the values in risksTypes
    protected const risksTypes = ['noRisks','risksPhysical', 'risksIntegrity', 'risksMental', 'risksEmotional', 'risksSocial', 'otherRisks'];
    protected const burdensRisksContributorsNode = 'burdensRisksContributors';
    protected const burdensRisksCompensationNode = 'compensation';
    protected const informingNode = 'informing';
    protected const informingAlways = 'always';
    protected const informingConsent = 'consent';
    protected const findingNode = 'finding';
    protected const feedbackNode = 'feedback';
    // consent
    protected const consentNode = 'consent';
    protected const voluntaryNode = 'voluntary';
    protected const voluntaryNotApplicable = 'notApplicable'; // must equal one of the values in $voluntaryTypes
    protected const voluntaryConsentNo = 'no'; // must equal one value in $voluntaryTypes and one ine $consentTypes
    protected const voluntaryTypes = ['projectdetails.pages.consent.voluntary.types.yes' => 'yes', 'projectdetails.pages.consent.voluntary.types.no' => 'no', 'projectdetails.pages.consent.voluntary.types.notApplicable' => 'notApplicable'];
    protected const voluntaryYesDescription = 'voluntaryYesDescription';
    protected const consentNotApplicable = 'notApplicable'; // must equal on of the values in $consentTypes
    protected const consentOral = 'oral'; // must equal one of the values in $consentTypes
    protected const consentTypesAny = ['written','digital','oral']; // must equal the values in $consentTypes; contains all types of consent where a consent gets created
    protected const consentOther = 'other'; // must equal one of the values in $consentTypes
    protected const consentTypes = ['projectdetails.pages.consent.consent.types.written' => 'written', 'projectdetails.pages.consent.consent.types.digital' => 'digital', 'projectdetails.pages.consent.consent.types.oral' => 'oral', 'projectdetails.pages.consent.consent.types.noConsent' => 'no', 'projectdetails.pages.consent.consent.types.notApplicable' => 'notApplicable', 'projectdetails.pages.consent.consent.types.other' => 'other'];
    protected const consentOtherDescription = 'otherDescription';
    protected const terminateConsNode = 'terminateCons';
    protected const terminateConsParticipationNode = 'participation'; // description of terminate cons for participation document
    protected const terminateParticipantsNode = 'terminateParticipants';
    protected const terminateParticipantsOther = 'terminateParticipantsOther'; // must equal one value in terminateParticipantsTypes
    protected const terminateParticipantsTypes = ['projectdetails.pages.consent.terminateParticipants.types.remove' => 'remove', 'projectdetails.pages.consent.terminateParticipants.types.removePartial' => 'removePartial', 'projectdetails.pages.consent.terminateParticipants.types.terminateParticipantsOther' => 'terminateParticipantsOther'];
    protected const terminateCriteriaNode = 'terminateCriteria';
    protected const chosen2Node = 'chosen2';
    // compensation
    protected const compensationNode = 'compensation';
    protected const compensationTypeNode = 'type';
    protected const compensationNo = 'noCompensation';
    protected const compensationMoney = 'money';
    protected const compensationHours = 'hours';
    protected const compensationLottery = 'lottery';
    protected const compensationOther = 'compensationOther';
    protected const compensationTypes = ['noCompensation','money','hours','lottery','voucher','compensationOther']; // must equal the values of the preceding variables
    protected const lotteryStart = 'lotteryStart'; // name of text field for lottery title in awarding
    protected const lotteryStartOtherDescription = 'lotteryStartOtherDescription'; // description of other option in lottery start
    protected const valueMin = 0.01; // minimum for money and hours
    protected const valueMax = 99999; // maximum for money and hours
    protected const valueTypes = ['projectdetails.pages.compensation.amount.real' => 'real', 'projectdetails.pages.compensation.amount.flat' => 'flat'];
    protected const valueSuffix = 'Value';
    protected const amountSuffix = 'Amount';
    protected const amountFlat = 'flat';
    protected const terminateNode = 'terminate';
    protected const terminateTypesDescription = ['nothing','terminateOther']; // terminate types for which a description must be given
    protected const moneyHourAdditionalNode = 'additional';
    protected const hourAdditionalNode2 = 'additional2';
    protected const moneyFurther = 'moneyFurther';
    protected const awardingNode = 'awarding';
    protected const awardingTypes = ['money' => ['projectdetails.pages.compensation.awarding.money.immediately' => 'immediately','projectdetails.pages.compensation.awarding.money.later' => 'later', 'projectdetails.pages.compensation.awarding.money.transfer' => 'transfer', 'projectdetails.pages.compensation.awarding.money.external' => 'external', 'projectdetails.pages.compensation.awarding.money.moneyOther' => 'other'],
        'hours' => ['projectdetails.pages.compensation.awarding.hours.immediately' => 'immediately', 'projectdetails.pages.compensation.awarding.hours.post' => 'post', 'projectdetails.pages.compensation.awarding.hours.later' => 'later', 'projectdetails.pages.compensation.awarding.hours.hoursOther' => 'other'],
        'lottery' => ['projectdetails.pages.compensation.awarding.lottery.later' => 'later', 'projectdetails.pages.compensation.awarding.lottery.deliver' => 'deliver', 'projectdetails.pages.compensation.awarding.lottery.external' => 'external', 'projectdetails.pages.compensation.awarding.lottery.lotteryOther' => 'other'],
        'voucher' => ['projectdetails.pages.compensation.awarding.voucher.immediately' => 'immediately', 'projectdetails.pages.compensation.awarding.voucher.later' => 'later', 'projectdetails.pages.compensation.awarding.voucher.deliver' => 'deliver', 'projectdetails.pages.compensation.awarding.voucher.voucherOther' => 'other']];
    protected const awardingLater = 'later'; // must equal one value of a subarray in $awardingType
    protected const awardingDeliver = 'deliver'; // must equal one value of a subarray in $awardingType
    protected const laterTypes = ['projectdetails.pages.compensation.awarding.laterEnd.code' => 'code', 'projectdetails.pages.compensation.awarding.laterEnd.name' => 'name', 'projectdetails.pages.compensation.awarding.laterEnd.laterEndOther' => 'laterEndOther'];
    protected const laterEndOther = 'laterEndOther'; // must equal one value in $laterTypes
    protected const laterTypesName = 'laterInformation'; // widget name
    protected const laterOtherDescription = 'laterOtherDescription'; // widget name of later type other
    protected const lotteryTypes = ['projectdetails.pages.compensation.awarding.lottery.result.types.eMail' => 'eMail', 'projectdetails.pages.compensation.awarding.lottery.result.types.mail' => 'mail', 'projectdetails.pages.compensation.awarding.lottery.result.types.phone' => 'phone', 'projectdetails.pages.compensation.awarding.lottery.result.types.local' => 'local', 'projectdetails.pages.compensation.awarding.lottery.result.types.resultOther' => 'resultOther'];
    protected const lotteryResultOther = 'resultOther'; // must equal one value in $lotteryTypes
    protected const lotteryDeliverTypes = ['projectdetails.pages.compensation.awarding.lottery.deliverTypes.mail' => 'mail', 'projectdetails.pages.compensation.awarding.lottery.deliverTypes.eMail' => 'eMail', 'projectdetails.pages.compensation.awarding.lottery.deliverTypes.local' => 'local'];
    protected const voucherDeliverTypes = ['projectdetails.pages.compensation.awarding.voucher.deliverTypes.mail' => 'mail', 'projectdetails.pages.compensation.awarding.voucher.deliverTypes.eMail' => 'eMail'];
    protected const awardingOtherDescription = 'awardingOtherDescription'; // widget name of text field for awarding description of other compensation
    protected const compensationTextNode = 'furtherDescription';
    protected const compensationVoluntaryNode = 'compensationVoluntary';
    // texts
    protected const textsNode = 'texts';
    protected const introNode = 'intro';
    protected const introTemplate = 'introTemplate';
    protected const goalsNode = 'goals';
    protected const procedureNode = 'procedure';
    protected const proNode = 'pro';
    protected const proTemplate = 'proTemplate';
    protected const proTemplateText = 'proTemplateText';
    protected const conNode = 'con';
    protected const conTemplate = 'conTemplate';
    protected const findingTextNode = 'findingText';
    protected const findingTemplate = 'findingTextTemplate';
    // legal
    protected const legalNode = 'legal';
    protected const liabilityNode = 'liability';
    protected const insuranceNode = 'insurance';
    protected const apparatusNode = 'apparatus';
    protected const insuranceWayNode = 'insuranceWay';
    protected const legalTypes = ['liability','insurance','apparatus','insuranceWay']; // must equal the values of the preceding variables
    // data privacy
    protected const privacyNotApplicable = 'notApplicable'; // value of an option saying that the question is not applicable
    protected const privacyNode = 'dataPrivacy';
    protected const processingNode = 'processing';
    protected const createNode = 'create';
    protected const createTool = 'tool'; // must equal one value in $createTypes
    protected const createSeparate = 'separate'; // must equal one value in $createTypes
    protected const createTypes = ['projectdetails.pages.dataPrivacy.create.types.tool' => 'tool', 'projectdetails.pages.dataPrivacy.create.types.separate' => 'separate', 'projectdetails.pages.dataPrivacy.create.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.create.types.separateLater' => 'separateLater', 'projectdetails.pages.dataPrivacy.create.types.notApplicable' => 'notApplicable'];
    protected const createVerificationNode = 'verification';
    protected const verificationTypes = ['projectdetails.pages.dataPrivacy.create.verification.types.verified' => 'verified', 'projectdetails.pages.dataPrivacy.create.verification.types.unverified' => 'unverified'];
    protected const confirmIntroNode = 'confirmIntro'; // confirm that introduction has been read, also used for data reuse
    protected const responsibilityNode = 'responsibility';
    protected const responsibilityTypes = ['projectdetails.pages.dataPrivacy.responsibility.types.onlyOwn' => 'onlyOwn', 'projectdetails.pages.dataPrivacy.responsibility.types.onlyOther' => 'onlyOther', 'projectdetails.pages.dataPrivacy.responsibility.types.multiple' => 'multiple', 'projectdetails.pages.dataPrivacy.responsibility.types.private' => 'private', 'projectdetails.pages.dataPrivacy.responsibility.types.notApplicable' => 'notApplicable'];
    protected const responsibilityPrivate = ['projectdetails.pages.dataPrivacy.responsibility.types.private' => 'private']; // must equal one element in $responsibilityTypes
    protected const responsibilityOnlyOwn = 'onlyOwn'; // must equal one value in $responsibilityTypes
    protected const transferOutsideNode = 'transferOutside';
    protected const transferOutsideTypes = ['projectdetails.pages.dataPrivacy.transferOutside.types.yes' => 'yes', 'projectdetails.pages.dataPrivacy.transferOutside.types.no' => 'no', 'projectdetails.pages.dataPrivacy.transferOutside.types.notApplicable' => 'notApplicable'];
    protected const transferOutsideNo = 'no'; // must equal one value in $transferOutsideTypes
    protected const dataOnlineNode = 'dataOnline'; // question whether ip-addresses are collected
    protected const dataOnlineTypes = ['projectdetails.pages.dataPrivacy.dataOnline.types.ipTechnical' => 'ipTechnical', 'projectdetails.pages.dataPrivacy.dataOnline.types.ipResearch' => 'ipResearch', 'projectdetails.pages.dataPrivacy.dataOnline.types.ipNo' => 'ipNo'];
    protected const dataOnlineTechnical = 'ipTechnical'; // must equal one value in $dataOnlineTypes
    protected const dataOnlineResearch = 'ipResearch'; // must equal one value in $dataOnlineTypes
    protected const dataOnlineProcessingNode = 'dataOnlineProcessing';
    protected const dataOnlineProcessingTypes = ['projectdetails.pages.dataPrivacy.dataOnlineProcessing.types.separate' => 'separate', 'projectdetails.pages.dataPrivacy.dataOnlineProcessing.types.linked' => 'linked', 'projectdetails.pages.dataPrivacy.dataOnlineProcessing.types.research' => 'research'];
    protected const dataOnlineProcessingLinked = 'linked'; // must equal one value in $dataOnlineProcessingTypes
    protected const dataOnlineProcessingResearch = 'research'; // must equal one value in $dataOnlineProcessingTypes
    protected const dataPersonalNode = 'dataPersonal'; // question whether research data are personal
    protected const dataPersonalTypes = ['projectdetails.pages.dataPrivacy.dataPersonal.types.personal' => 'personal', 'projectdetails.pages.dataPrivacy.dataPersonal.types.personalMaybe' => 'personalMaybe', 'projectdetails.pages.dataPrivacy.dataPersonal.types.personalNo' => 'personalNo'];
    protected const dataPersonalMaybe = 'personalMaybe'; // must equal one value in $dataPersonalTypes
    protected const dataPersonal = ['personal','personalMaybe']; // values must equal the values in $dataPersonalTypes
    protected const markingNode = 'marking'; // how the research data are marked
    protected const markingTypes = ['projectdetails.pages.dataPrivacy.marking.types.external' => 'external', 'projectdetails.pages.dataPrivacy.marking.types.internal' => 'internal', 'projectdetails.pages.dataPrivacy.marking.types.name' => 'name', 'projectdetails.pages.dataPrivacy.marking.types.no' => 'no', 'projectdetails.pages.dataPrivacy.marking.types.other' => 'other']; // values must equal the values of the following variables
    protected const markingValues = ['external','internal','name'];
    protected const markingExternal = 'external';
    protected const markingInternal = 'internal';
    protected const markingName = 'name';
    protected const markingNo = 'no';
    protected const markingOther = 'other';
    protected const internalTypes = ['projectdetails.pages.dataPrivacy.marking.internal.types.pattern' => 'pattern', 'projectdetails.pages.dataPrivacy.marking.internal.types.own' => 'own', 'projectdetails.pages.dataPrivacy.marking.internal.types.contributors' => 'contributors'];
    protected const markingSubTypes = ['external' => ['projectdetails.pages.dataPrivacy.marking.external.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.marking.external.types.list' => 'list', 'projectdetails.pages.dataPrivacy.marking.external.types.generation' => 'generation'], 'pattern' => ['projectdetails.pages.dataPrivacy.marking.internal.pattern.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.marking.internal.pattern.types.generation' => 'generation', 'projectdetails.pages.dataPrivacy.marking.internal.pattern.types.list' => 'list'], 'own' => ['projectdetails.pages.dataPrivacy.marking.internal.own.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.marking.internal.own.types.list' => 'list'], 'contributors' => ['projectdetails.pages.dataPrivacy.marking.internal.contributors.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.marking.internal.contributors.types.list' => 'list', 'projectdetails.pages.dataPrivacy.marking.internal.contributors.types.marking' => 'marking', 'projectdetails.pages.dataPrivacy.marking.internal.contributors.types.generation' => 'generation']]; // one subarray for external, three subarrays for internal
    protected const markingDataResearchTypes = ['list','generation']; // must equal the values in sub-arrays of $markingSubTypes.
    protected const markingList = 'list'; // must equal one value in sub-arrays of $markingSubTypes
    protected const generation = 'generation'; // node name, must also equal one value in sub-arrays of $markingSubTypes
    protected const codePersonal = 'codePersonal'; // node name, must also equal one value in sub-array of $markingSubTypes
    protected const markingFurtherNode = 'markingFurther';
    protected const markingSuffix = 'Second'; // suffix for marking widgets that are created twice
    protected const listNode = 'list';
    protected const listTypes = ['name','eMail','studentNumber','token','sona','prolific','listIP','listOther'];
    protected const listOther = 'listOther'; // must equal one value in $listTypes
    protected const dataResearchNode = 'dataResearch';
    protected const dataResearchTypesAll = ['demographic','observation','survey','audio','photo','video','instrumental','ip','dataResearchOther','ethnic','political','union','sexual','brainStructure','biometric','health','genetic','hair','saliva','bloodSample','dataResearchSpecialOther'];
    protected const dataResearchTypes = ['demographic','observation','survey','audio','photo','video','instrumental','ip','dataResearchOther']; // must equal the values in $dataResearchTypesAll
    protected const dataSpecialTypes = ['ethnic','political','union','sexual','brainStructure','biometric','health','genetic','hair','saliva','bloodSample','dataResearchSpecialOther']; // must equal the values in $dataResearchTypesAll
    protected const dataResearchTextFieldsAll = ['demographic','observation','survey','instrumental','dataResearchOther','biometric','health','dataResearchSpecialOther']; // options which need further description. Values must equal the values in $dataResearchTypes
    protected const dataResearchTextFields = ['demographic','observation','survey','instrumental','dataResearchOther']; // must equal the values in $dataResearchTextFieldsAll
    protected const dataSpecialTextFields = ['biometric','health','dataResearchSpecialOther']; // must equal the values in $dataResearchTextFieldsAll
    protected const dataResearchSpecialOther = 'dataResearchSpecialOther'; // must equal one value in $dataResearchTypes
    protected const anonymizationNode = 'anonymization';
    protected const anonymizationTypes = ['grouping','convert','delete','alienate','preprocess','anonymizationOther','anonymizationNo'];
    protected const anonymizationOther = 'anonymizationOther'; // must equal one value in $anonymizationTypes
    protected const anonymizationNo = 'anonymizationNo'; // must equal one value in $anoymizationTypes
    protected const storageNode = 'storage';
    protected const storageTypes = ['projectdetails.pages.dataPrivacy.storage.types.delete' => 'delete', 'projectdetails.pages.dataPrivacy.storage.types.keep' => 'keep']; // values must equal the values of the following two variables
    protected const storageDelete = 'delete';
    protected const personalKeepNode = 'personalKeep';
    protected const personalKeepTypes = ['documentation','reuse','teaching','demonstration'];
    protected const personalKeepReuse = 'reuse'; // must equal one value in $personalKeepTypes
    protected const personalKeepTeaching = 'teaching'; // must equal one value in $personalKeepTypes
    protected const personalKeepDemonstration = 'demonstration'; // must equal one value in $personalKeepTypes
    protected const personalKeepConsentNode = 'personalKeepConsent';
    protected const personalKeepConsentTypes = ['projectdetails.pages.dataPrivacy.personalKeepConsent.types.obligatory' => 'obligatory', 'projectdetails.pages.dataPrivacy.personalKeepConsent.types.optional' => 'optional'];
    protected const purposeResearchNode = 'purposeResearch';
    protected const purposeResearchTypes = ['purposeNo','compensation','relatable','contact','technical'];
    protected const purposeRelatable = 'relatable'; // must equal one value in $purposeResearchTypes
    protected const purposeTechnical = 'technical'; // must equal one value in $purposeResearchTypes
    protected const purposeNo = 'purposeNo'; // must equal one value in $purposeResearchTypes
    protected const purposeFurtherNode = 'purposeFurther';
    protected const purposeFurtherTypes = ['purposeNo','compensation','contact','contactResult','technical']; // values (except contactResult) must equal the values in $purposeResearchTypes
    protected const allPurposeTypes = ['compensation','relatable','contact','contactResult','technical']; // $purposeResearchTypes and $purposeFurtherTypes merged, but without 'no purpose'. Separate variable to keep order of sub-questions
    protected const purposeCompensation = 'compensation'; // must equal one value in $purposeResearchTypes or $purposeFurtherTypes
    protected const relatableNode = 'relatableSub';
    protected const relatableTypes = ['relatableContactResult','relatableFeedback','relatableDeletion','relatableLinking'];
    protected const purposeDataNode = 'purposeData';
    protected const purposeDataTypes = ['name','eMail','phone','address','studentNumber','token','sona','prolific','iban','purposeDataOther'];
    protected const purposeDataOther = 'purposeDataOther'; // must equal one value in $purposeDataTypes
    protected const markingRemoveNode = 'markingRemove';
    protected const markingRemoveTypes = ['projectdetails.pages.dataPrivacy.markingRemove.types.markingRemoveImmediately' => 'markingRemoveImmediately', 'projectdetails.pages.dataPrivacy.markingRemove.types.markingRemoveLater' => 'markingRemoveLater'];
    protected const markingRemoveLater = 'markingRemoveLater'; // must equal one value in $markingRemoveTypes
    protected const laterDescription = 'laterDescription';
    protected const markingRemoveMiddleNode = 'middle';
    protected const markingRemoveMiddleTypes = ['middleList','middleCodeChange','middleCodeRemove','middleName'];
    protected const personalRemoveNode = 'personalRemove';
    protected const personalRemoveTypes = ['projectdetails.pages.dataPrivacy.personalRemove.types.immediately' => 'immediately', 'projectdetails.pages.dataPrivacy.personalRemove.types.keep' => 'keep', 'projectdetails.pages.dataPrivacy.personalRemove.types.keepFurther' => 'keepFurther'];
    protected const personalRemoveImmediately = 'immediately'; // must equal one value in $personalRemoveTypes
    protected const personalRemoveKeep = 'keep'; // must equal one value in $personalRemoveTypes
    protected const keepDescription = 'keepDescription';
    protected const accessNode = 'access';
    protected const accessTypes = ['contributors','contributorsPart','institution','contributorsOther','accessExternal','dataService'];
    protected const accessContributorsOther = 'contributorsOther'; // must equal one value in $accessTypes
    protected const accessOthers = ['contributorsPart','institution']; // values must equal the values of $accessTypes
    protected const accessOrderProcessing = ['contributorsOther','accessExternal','dataService']; // values must equal the values of $accessTypes. Access types for which order processing is asked
    protected const transferNode = 'transfer';
    protected const orderProcessingNode = 'orderProcessing';
    protected const orderProcessingKnownNode = 'orderProcessingKnown';
    protected const orderProcessingKnownTypes = ['projectdetails.pages.dataPrivacy.orderProcessingKnown.types.knownYes' => 'knownYes','projectdetails.pages.dataPrivacy.orderProcessingKnown.types.knownPart' => 'knownPart', 'projectdetails.pages.dataPrivacy.orderProcessingKnown.types.knownNo' => 'knownNo'];
    protected const orderProcessingYesTypes = ['knownYes','knownPart']; // value must equal the values in $orderProcessingKnownTypes
    protected const orderProcessingDescriptionNode = 'orderProcessingDescription';
    protected const orderProcessingKnownTexts = ['orderProcessingStart','orderProcessingMiddle','orderProcessingEnd'];
    protected const codeCompensationNode = 'codeCompensation';
    protected const codeCompensationTypes = ['projectdetails.pages.dataPrivacy.codeCompensation.types.codeExternal' => 'codeExternal', 'projectdetails.pages.dataPrivacy.codeCompensation.types.codeInternal' => 'codeInternal'];
    protected const codeCompensationExternal = 'codeExternal'; // must equal one value in $codeCompensationTypes
    protected const codeCompensationInternal = 'codeInternal'; // must equal one value in $codeCompensationTypes
    protected const codeCompensationInternalTypes = ['projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.types.pattern' => 'pattern', 'projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.types.own' => 'own', 'projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.types.contributors' => 'contributors'];
    protected const codeCompensationSubTypes = ['external' => ['projectdetails.pages.dataPrivacy.codeCompensation.codeExternal.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.codeCompensation.codeExternal.types.generation' => 'generation'], 'pattern' => ['projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.pattern.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.pattern.types.generation' => 'generation'], 'contributors' => ['projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.contributors.types.anonymous' => 'anonymous', 'projectdetails.pages.dataPrivacy.codeCompensation.codeInternal.contributors.types.generation' => 'generation']]; // one subarray for external, two subarrays for internal
    protected const codeCompensationKeys = ['external','pattern','contributors']; // must equal the keys in $codeCompensationSubTypes and the values in $codeCompensationInternalTypes
    protected const codeCompensationPersonal = 'codePersonal'; // node name
    protected const processingFurtherNode = 'processingFurther';
    protected const purposeNode = 'purpose';
    // data reuse
    protected const dataReuseNode = 'dataReuse'; // used for page and for question on page
    protected const dataReuseTypes = ['tool' => ['projectdetails.pages.dataReuse.dataReuse.types.yes' => 'yes', 'projectdetails.pages.dataReuse.dataReuse.types.no' => 'no'], 'noTool' => ['projectdetails.pages.dataReuse.dataReuse.types.anonymous' => 'anonymous', 'projectdetails.pages.dataReuse.dataReuse.types.anonymized' => 'anonymized', 'projectdetails.pages.dataReuse.dataReuse.types.personal' => 'personal', 'projectdetails.pages.dataReuse.dataReuse.types.no' => 'no']]; // one element if privacy document should/can be created by the tool and one if not
    protected const dataReuseHowNode = 'dataReuseHow';
    protected const dataReuseHowTypes = ['projectdetails.pages.dataReuse.dataReuseHow.types.class0' => 'class0', 'projectdetails.pages.dataReuse.dataReuseHow.types.class1' => 'class1', 'projectdetails.pages.dataReuse.dataReuseHow.types.class2' => 'class2', 'projectdetails.pages.dataReuse.dataReuseHow.types.class3' => 'class3', 'projectdetails.pages.dataReuse.dataReuseHow.types.own' => 'own'];
    protected const dataReuseHowOwn = ['projectdetails.pages.dataReuse.dataReuseHow.types.own' => 'own']; // must equal one entry in $dataReuseHowTypes
    protected const dataReuseSelfNode = 'dataReuseSelf';

    // functions

    /** Get the addressee from the current request. If the route parameters do not contain the current IDs of study, group and measure time point, use getAddressee.
     * @param Request $request
     * @return string addressee
     */
    protected function getAddresseeFromRequest(Request $request): string {
        return $this->getAddressee($this->xmlToArray($this->getMeasureTimePointNode($request,$request->get('_route_params'))->{self::groupsNode}));
    }

    /** Get the addressee from a groups array. Can be used if the route parameters of the request do not contain the study, group and measure time point IDs, otherwise getAddresseeFromRequest can be used.
     * @param array $groups array containing the groups elements
     * @return string addressee
     */
    protected function getAddressee(array $groups): string {
        $examined = $groups[self::examinedPeopleNode];
        $isWards = $examined!=='' && array_key_exists(self::wardsExaminedNode,$examined);
        $maxAge = $this->getIntFromString($groups[self::maxAge],101); // if upper limit of minAge or maxAge is changed in groups template, it maybe has to be changed here, too
        return ($this->getIntFromString($groups[self::minAge],101)<16 || ($maxAge>=0 && $maxAge<18 && $isWards)) ? self::addresseeChildren : ($isWards ? self::addresseeWards : self::addresseeParticipants);
    }

    /** Returns the translated string for the addressee.
     * @param string $addressee translation key of the addressee
     * @param bool $isThirdParty if true, then the third party addressees are translated, otherwise the participants
     * @param bool $addPronoun if true and the $addressee is not participants, a pronoun is added at the start of the string
     * @param bool $onlyPronoun if \$isThirdParty is false and \$addPronoun is true, then, if true, only the pronoun is returned
     * @return string translated addressee
     */
    protected function getAddresseeString(string $addressee, bool $isThirdParty = true, bool $addPronoun = false, bool $onlyPronoun = false): string {
        $translationPrefix = 'projectdetails.addressee.';
        if ($onlyPronoun) {
            return $this->translateString($translationPrefix.'participants.pronoun');
        }
        else {
            $returnString = $this->translateString($translationPrefix.($isThirdParty ? 'thirdParties.' : 'participants.').$addressee);
            if ($addPronoun && $addressee!==self::addresseeParticipants) {
                $returnString = $this->translateString($translationPrefix.'participants.pronoun'.$addressee).$this->translateString($translationPrefix.'participants.'.$addressee);
            }
            return $returnString;
        }
    }

    /** Checks if the question for using a template is answered with either template or self-written text.
     * @param string $choice answer to the template question
     * @return bool true if either template or self-written text is chosen, false otherwise
     */
    protected function getTemplateChoice(string $choice): bool {
        return in_array($choice,[self::template,self::templateText]);
    }

    /** Checks whether inputs in informationIII are necessary.
     * @param array|string $information array containing the information of the information page
     * @return bool true if inputs in informationIII are necessary, false otherwise
     */
    protected function getInformationIII(array|string $information): bool {
        if ($information!=='' && $information[self::chosen]=='0') {
            $information = $information[self::informationAddNode];
            return in_array($information[self::chosen],self::preContentIncomplete) && $information[self::complete]=='0';
        }
        return false;
    }

    /** Checks whether the data privacy document is not or can not be created by the tool.
     * @param array $privacyArray array containing the data privacy data
     * @return bool true if a self-formulated pdf must be added, false otherwise
     */
    protected function getPrivacyNoTool(array $privacyArray): bool {
        return $privacyArray[self::createNode][self::chosen]===self::createSeparate || in_array($privacyArray[self::responsibilityNode] ?? '',['onlyOther','multiple','private']) || ($privacyArray[self::transferOutsideNode] ?? '')==='yes' || ($privacyArray[self::markingNode][self::chosen] ?? '')===self::markingOther;
    }

    /** Adds a prefix to each element in the array.
     * @param array $array array whose elements get prefixed
     * @param string $prefix prefix to be added
     * @return array $array with each element prefixed
     */
    protected function prefixArray(array $array, string $prefix): array {
        return substr_replace($array,$prefix,0,0);
    }

    // methods

    /** Adds a new node which is either a study, a group, or a measure time point node.
     * @param SimpleXMLElement $element node where the node gets appended
     * @param string $nodeName name of the node that is appended. Must equal 'study', 'group' or 'measureTimePoint'
     * @param string $nameContent content of the 'name' node of the created node if $nodeName equals 'study' or 'group'
     * @param int|null $copy index of node that should be copied (only nodes whose name equals $nodeName are counted) or null if an empty node should be created
     * @return void
     */
    protected function addMeasurement(SimpleXMLElement $element, string $nodeName, string $nameContent, ?int $copy = null): void {
        $isNotMeasure = $nodeName!==self::measureTimePointNode;
        if ($copy!==null) {
            $newNode = simplexml_import_dom(dom_import_simplexml($element)->appendChild(dom_import_simplexml($element->{$nodeName}[$copy])->cloneNode(true)));
            if ($isNotMeasure) {
                $newNode->{self::nameNode} = $nameContent;
            }
        }
        else {
            $newNode = $element->addChild($nodeName);
            if ($isNotMeasure) {
                $newNode->addChild(self::nameNode,$nameContent);
                if ($nodeName==self::studyNode) {
                    $newNode = $newNode->addChild(self::groupNode);
                    $newNode->addChild(self::nameNode);
                }
                $newNode = $newNode->addChild(self::measureTimePointNode);
            }
            // groups
            $groupNode = $newNode->addChild(self::groupsNode);
            $this->addChildNodes($groupNode,[self::minAge,self::maxAge,self::examinedPeopleNode,self::peopleDescription]);
            $criteria = $groupNode->addChild(self::criteriaNode);
            $includeNode = $criteria->addChild(self::criteriaIncludeNode);
            $this->addChildNodes($includeNode,[self::noCriteriaNode,self::criteriaNode]);
            $includeNode->{self::criteriaNode}->addChild(self::criteriaIncludeNode.'0',str_replace('0','X',$this->translateString('projectdetails.pages.groups.criteria.include.addressee',[self::addressee => 'other', 'limits' => 'sameLimit', 'minAge' => '0'])));
            $this->addChildNodesChosen($groupNode,[self::closedNode]);
            $this->addChildNodes($criteria->addChild(self::criteriaExcludeNode),[self::noCriteriaNode,self::criteriaNode]);
            $this->addChildNodes($groupNode->addChild(self::sampleSizeNode),[self::sampleSizeTotalNode,self::sampleSizeFurtherNode,self::sampleSizePlanNode]);
            $this->addChildNodes($groupNode->addChild(self::recruitment),[self::recruitmentTypesNode,self::descriptionNode]);
            // information(II)
            $this->addInformationSubNodes($newNode->addChild(self::informationNode));
            $newNode->addChild(self::informationIINode);
            // informationIII
            $newNode->addChild(self::informationIIINode);
            // measures
            $measuresNode = $newNode->addChild(self::measuresNode);
            $this->addChildNodes($measuresNode->addChild(self::measuresNode),[self::measuresTypesNode,self::descriptionNode]);
            $this->addChildNodes($measuresNode->addChild(self::interventionsNode),[self::interventionsTypesNode]);
            $this->addChosenNode($measuresNode,self::otherSourcesNode);
            $this->addChosenNode($measuresNode,self::loanNode);
            $this->addChosenNode($measuresNode,self::locationNode)->addChild(self::descriptionNode);
            $measuresNode->addChild(self::presenceNode);
            $this->addChildNodes($measuresNode->addChild(self::durationNode),self::durationTypes);
            // burdens/risks
            $burdensRisksNode = $newNode->addChild(self::burdensRisksNode);
            $this->addChildNodes($burdensRisksNode->addChild(self::burdensNode),[self::burdensTypesNode]);
            $this->addChildNodes($burdensRisksNode->addChild(self::risksNode),[self::risksTypesNode]);
            $this->addChosenNode($burdensRisksNode,self::burdensRisksContributorsNode);
            $this->addChildNodesChosen($burdensRisksNode,[self::findingNode,self::feedbackNode]);
            // consent
            $consentNode = $newNode->addChild(self::consentNode);
            $this->addChildNodesChosen($consentNode,[self::voluntaryNode,self::consentNode]);
            $this->addChosenNode($consentNode,self::terminateConsNode);
            $this->addChosenNode($consentNode,self::terminateParticipantsNode);
            $consentNode->addChild(self::terminateCriteriaNode);
            // compensation
            $newNode->addChild(self::compensationNode)->addChild(self::compensationTypeNode);
            // texts
            $newNode->addChild(self::textsNode);
            // legal
            $newNode->addChild(self::legalNode);
            // data privacy
            $privacyNode = $newNode->addChild(self::privacyNode);
            $privacyNode->addChild(self::processingNode);
            $this->addChosenNode($privacyNode,self::createNode);
            $privacyNode->addChild(self::dataReuseNode);
            // data reuse
            $newNode->addChild(self::dataReuseNode)->addChild(self::confirmIntroNode);
            // contributor (Beteiligte)
            $contributor = $newNode->addChild(self::contributorNode);
            foreach (self::tasksNodes as $task) {
                $contributor->addChild($task);
            }
        }
    }

    /** Adds a 'chosen', a 'description', and an 'additional' node.
     * @param SimpleXMLElement $element Node where the children get appended.
     * @return void
     */
    protected function addInformationSubNodes(SimpleXMLElement $element): void {
        $element->addChild(self::chosen);
        $element->addChild(self::descriptionNode);
        $element->addChild(self::informationAddNode)->addChild(self::chosen);
    }

    /** For each value in $nodes, a child of \$element with the same name is created. This child is added another 'chosen' child.
     * @param SimpleXMLElement $element node where the children get appended
     * @param array $nodeNames names of the children
     * @return void
     */
    protected function addChildNodesChosen(SimpleXMLElement $element, array $nodeNames): void {
        foreach ($nodeNames as $name) {
            $element->addChild($name)->addChild(self::chosen);
        }
    }
}