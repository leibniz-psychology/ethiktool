<?php

namespace App\Traits;

use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/** Contains variables with all questions in the Ethiktool as well as variables and functions regarding the review process. */
trait ReviewProcessTrait
{
    protected const reviewProcess = 'reviewProcess'; // node name for type of review process (following two variables)
    protected const reviewProcessFull = 'full';
    protected const reviewProcessShort = 'short';
    protected const reviewShortNoDocs = 'shortNoDocs'; // without participant documents
    protected const reviewShortDocs = 'shortDocs'; // with participants documents that are reviewed
    protected const reviewShortService = 'shortService'; // with participant documents that are not reviewed
    protected const reviewShortRequested = 'shortRequested'; // without participant documents because funding is requested
    protected const reviewShortBegun = 'shortBegun'; // without participant documents because data collection has already started
    protected const reviewFullRequested = 'fullRequested'; // without participant documents because funding is requested
    protected const reviewFullBegun = 'fullBegun'; // without participant documents because data collection has already started
    protected const reviewFullDocs = 'fullDocs'; // with participant documents
    protected const reviewDocs = ['shortDocs','shortService','fullDocs']; // types of review processes for which participant documents are created. Must equal some of the preceding variables
    protected const reviewShortChoose = ['EUB','TUD','testCommittee']; // committees where, for short applications, it can be chosen whether participant documents should be created

    /** Each element is one projectdetails page. The structure of each page-element is equivalent to the structure of the xml-file. Each sub-element is one sub-element of the respective xml node. Each of these elements contains all application types for which the question is asked. If the question is a sub-question (i.e., a child of another question in the xml file), the application types are the same as for the parent question. Used if the review process changes to add/remove nodes in the xml file.
     */
    protected const reviewQuestions = [
        // groups
        'groups' => [
            'minAge' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'maxAge' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'examinedPeople' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'peopleDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'closed' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'include' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'exclude' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'sampleSize' => ['fullBegun','fullRequested','fullDocs'],
            'recruitment' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'recruitmentFurther' => ['fullBegun','fullRequested','fullDocs']
        ], // groups
        // information
        'information' => [
            'pre' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preType' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'preContent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preComplete' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'post' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'attendance' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'documentTranslation' => ['fullBegun','fullRequested','fullDocs'],
            'documentTranslationPDF' => ['fullDocs']
        ], // information
        // consent
        'consent' => [
            'voluntary' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'consent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'terminateCons' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'participation' => ['shortDocs','shortService','fullDocs'],
            'terminateParticipants' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'terminateCriteria' => ['fullBegun','fullRequested','fullDocs']
        ], // consent
        // measures
        'measures' => [
            'procedure' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'measures' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresDescription' => ['fullBegun','fullRequested','fullDocs'],
            'measuresPDF' => ['fullBegun','fullRequested','fullDocs'],
            'interventions' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'interventionsDescription' => ['fullBegun','fullRequested','fullDocs'],
            'interventionsPDF' => ['fullBegun','fullRequested','fullDocs'],
            'otherSources' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'loan' => ['shortDocs','shortService','fullDocs'],
            'location' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'presence' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'duration' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // measures
        // burdensRisks
        'burdensRisks' => [
            'burdens' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensNoDescription' => ['fullBegun','fullRequested','fullDocs'],
            'risks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensRisksContributors' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'finding' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'feedback' => ['fullBegun','fullRequested','fullDocs']
        ], // burdensRisks
        // compensation
        'compensation' => [
            'type' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyFurther' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hoursDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'hoursawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'lotteryawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voucherawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'compensationOtherDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'compensationOtherawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'terminate' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'compensationVoluntary' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'furtherDescription' => ['fullBegun','fullRequested','fullDocs'],
        ], // compensation
        // texts
        'texts' => [
            'intro' => ['shortDocs','shortService','fullDocs'],
            'goals' => ['shortDocs','shortService','fullDocs'],
            'conflictText' => ['shortDocs','shortService','fullDocs'],
            'pro' => ['shortDocs','shortService','fullDocs'],
            'con' => ['shortDocs','shortService','fullDocs'],
            'findingText' => ['shortDocs','shortService','fullDocs']
        ], // texts
        // informationIII
        'informationIII' => [
            'goals' => ['shortDocs','shortService','fullDocs'],
            'infoBefore' => ['shortDocs','shortService','fullDocs'],
            'infoAfter' => ['shortDocs','shortService','fullDocs'],
            'explain' => ['shortDocs','shortService','fullDocs']
        ], // informationIII
        // legal
        'legal' => [
            'liability' => ['shortDocs','shortService','fullDocs'],
            'insurance' => ['shortDocs','shortService','fullDocs'],
            'apparatus' => ['shortDocs','shortService','fullDocs'],
            'insuranceWay' => ['shortDocs','shortService','fullDocs']
        ], // legal
        // data privacy
        'dataPrivacy' => [
            'processing' => ['fullBegun','fullRequested','fullDocs'],
            'create' => ['shortDocs','shortService','fullDocs'],
            'verification' => ['fullDocs'],
            'responsibility' => ['shortDocs','shortService','fullDocs'],
            'transferOutside' => ['shortDocs','shortService','fullDocs'],
            'dataOnline' => ['shortDocs','shortService','fullDocs'],
            'dataPersonal' => ['shortDocs','shortService','fullDocs'],
            'marking' => ['shortDocs','shortService','fullDocs'],
            'markingSecond' => ['shortDocs','shortService','fullDocs'],
            'markingFurther' => ['shortDocs','shortService','fullDocs'],
            'list' => ['shortDocs','shortService','fullDocs'],
            'dataResearch' => ['shortDocs','shortService','fullDocs'],
            'anonymization' => ['shortDocs','shortService','fullDocs'],
            'storage' => ['shortDocs','shortService','fullDocs'],
            'personalKeep' => ['shortDocs','shortService','fullDocs'],
            'access' => ['shortDocs','shortService','fullDocs'],
            'purposeResearch' => ['shortDocs','shortService','fullDocs'],
            'purposeFurther' => ['shortDocs','shortService','fullDocs'],
            'codeCompensation' => ['shortDocs','shortService','fullDocs'],
            'processingFurther' => ['shortDocs','shortService','fullDocs']
        ], // data privacy
        // data reuse
        'dataReuse' => [
            'confirmIntro' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'dataReuse' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'dataReuseHow' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseHowreuse' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseSelf' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs']
        ], // data reuse
        // contributor
        'contributor' => [
            'leader' => ['shortDocs','shortService','fullDocs'],
            'research' => ['shortDocs','shortService','fullDocs'],
            'experiment' => ['shortDocs','shortService','fullDocs'],
            'contact' => ['shortDocs','shortService','fullDocs'],
            'data' => ['shortDocs','shortService','fullDocs'],
            'other' => ['shortDocs','shortService','fullDocs']
        ]
    // no array for Complete form because nodes are always the same
    ]; // reviewQuestions

    /** Each element is one page of the Ethiktool. Each sub-element contains all Form elements for that page that are created using TypeAbstract->addFormElement(). The array of each sub-element contains the application types for which the question is asked. If the element is (part of) a sub-question, the application types are the same as for the main question.
     */
    protected const formTypeQuestions = [
        // no array for New form because, if on that page, no proposal is open, i.e., no application type is active
        // application data
        // votes
        'votes' => [
            'otherVote' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'description' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherVoteResult' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherVoteResultDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'instVote' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'reference' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'instVoteText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // votes
        // medicine
        'medicine' => [
            'medicine' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'medicineDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physician' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physicianDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'description' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // medicine
        // summary
        'summary' => [
            'summary' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // summary
        // Contributors
        'contributors' => [
            'name' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'institution' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'professorship' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'eMail' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'position' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'positionOtherText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'phone' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'leader' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'research' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'experiment' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'contact' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'data' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'other' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // Contributors
        // Projectdetails
        // groups
        'groups' => [
            'minAge' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'maxAge' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'unlimited' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'healthy' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'mental' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'medical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'institutional' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'wards' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'vulnerable' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'dependent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherPeople' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'peopleDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'closed' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'closedLecture' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'school' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'closedOther' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'closedOtherText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'includeText' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'include' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'excludeText' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'exclude' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'total' => ['fullBegun','fullRequested','fullDocs'],
            'furtherParticulars' => ['fullBegun','fullRequested','fullDocs'],
            'sampleSizePlan' => ['fullBegun','fullRequested','fullDocs'],
            'mailing' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'flyer' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'lecture' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'lectureOther' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'recruitmentPrivate' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'media' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'news' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'database' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'databaseText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'external' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'externalText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'multiplier' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'recruitmentOther' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'recruitmentOtherText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'recruitmentFurther' => ['fullBegun','fullRequested','fullDocs']
        ], // groups
        // information
        'information' => [
            'pre' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preType' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'preContent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preComplete' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preCompleteText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preCompleteType' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'preText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'post' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'postType' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'postText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'attendance' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'documentTranslation' => ['fullBegun','fullRequested','fullDocs'],
            'documentTranslationDescription' => ['fullBegun','fullRequested','fullDocs'],
            'documentTranslationPDF' => ['fullDocs']
        ], // information
        // consent
        'consent' => [
            'voluntary' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voluntaryParticipants' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voluntaryYesDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voluntaryDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'consent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'consentParticipants' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherDescriptionParticipants' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'consentDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'terminateCons' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'terminateConsDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'participation' => ['shortDocs','shortService','fullDocs'],
            'terminateParticipants' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'terminateParticipantsText' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'terminateCriteria' => ['fullBegun','fullRequested','fullDocs']
        ], // consent
        // measures
        'measures' => [
            'procedure' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'measuresObservation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresVideo' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresVideoText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresSurvey' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresSurveyText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresInstrumental' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresInstrumentalText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherMeasures' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherMeasuresText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measuresDescription' => ['fullBegun','fullRequested','fullDocs'],
            'measuresPDF' => ['fullBegun','fullRequested','fullDocs'],
            'noIntervention' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'interventionsSurvey' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'feedback' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'feedbackText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'everyday' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'everydayText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'stimulus' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'stimulusText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'tasks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'tasksText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'stimulation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'stimulationText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'psychological' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'psychologicalText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physicalText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'therapy' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'therapyText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'medical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'medicalText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'invasive' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'invasiveText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'other' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherText' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'interventionsDescription' => ['fullBegun','fullRequested','fullDocs'],
            'interventionsPDF' => ['fullBegun','fullRequested','fullDocs'],
            'otherSources' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherSourcesDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherSourcesPDF' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'loan' => ['shortDocs','shortService','fullDocs'],
            'receipt' => ['shortDocs','shortService','fullDocs'],
            'receiptText' => ['shortDocs','shortService','fullDocs'],
            'location' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'locationDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'presence' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'measureTime' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'breaks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs']
        ], // measures
        // burdensRisks
        'burdensRisks' => [
            'noBurdens' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'physical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'mental' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'emotional' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'sensitive'=> ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherBurdens' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensNoDescription' => ['fullBegun','fullRequested','fullDocs'],
            'burdensCompensation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensCompensationDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'noRisks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksPhysical' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksIntegrity' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksMental' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksEmotional' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksSocial' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'otherRisks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksCompensation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'risksCompensationDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensRisksContributors' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensRisksContributorsDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensRisksContributorsCompensation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'burdensRisksContributorsCompensationDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'finding' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'description' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'informing' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'feedback' => ['fullBegun','fullRequested','fullDocs'],
            'feedbackDescription' => ['fullBegun','fullRequested','fullDocs']
        ], // burdensRisks
        // compensation
        'compensation' => [
            'noCompensation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'money' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyValue' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyAmount' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'hours' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'hoursDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'hoursValue' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'hoursAmount' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'lottery' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'lotteryDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voucher' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'voucherDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'compensationOther' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'compensationOtherDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyFurther' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyFurtherDescription' => ['shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'terminate' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'terminateDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'moneyawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'moneylaterDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'moneylaterInformation' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'moneylaterOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'moneyexternalDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'moneyotherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hoursawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hourslaterDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hourslaterInformation' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hourslaterOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'hoursotherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryStartDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryStart' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryStartOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotterylaterDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotterylaterInformation' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotterylaterOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotterydeliver' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotterydeliverDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryexternalDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'lotteryotherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherawarding' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherlaterDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherlaterInformation' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherlaterOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherdeliver' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherdeliverDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'voucherotherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'awardingOtherDescription' => ['shortDocs','shortService','shortBegun','fullBegun','fullDocs'],
            'compensationVoluntary' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'compensationVoluntaryDescription' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
            'furtherDescription' => ['fullBegun','fullRequested','fullDocs']
        ], // compensation
        // texts
        'texts' => [
            'intro' => ['shortDocs','shortService','fullDocs'],
            'introTemplate' => ['shortDocs','shortService','fullDocs'],
            'goals' => ['shortDocs','shortService','fullDocs'],
            'conflictText' => ['shortDocs','shortService','fullDocs'],
            'conflictTextTemplate' => ['shortDocs','shortService','fullDocs'],
            'pro' => ['shortDocs','shortService','fullDocs'],
            'proTemplate' => ['shortDocs','shortService','fullDocs'],
            'proTemplateText' => ['shortDocs','shortService','fullDocs'],
            'con' => ['shortDocs','shortService','fullDocs'],
            'conTemplate' => ['shortDocs','shortService','fullDocs'],
            'findingText' => ['shortDocs','shortService','fullDocs'],
            'findingTextTemplate' => ['shortDocs','shortService','fullDocs']
        ], // texts
        // informationIII
        'informationIII' => [
            'goals' => ['shortDocs','shortService','fullDocs'],
            'infoBefore' => ['shortDocs','shortService','fullDocs'],
            'infoAfter' => ['shortDocs','shortService','fullDocs'],
            'explain' => ['shortDocs','shortService','fullDocs']
        ], // informationIII
        // legal
        'legal' => [
            'liability' => ['shortDocs','shortService','fullDocs'],
            'liabilityText' => ['shortDocs','shortService','fullDocs'],
            'insurance' => ['shortDocs','shortService','fullDocs'],
            'insuranceText' => ['shortDocs','shortService','fullDocs'],
            'apparatus' => ['shortDocs','shortService','fullDocs'],
            'apparatusText' => ['shortDocs','shortService','fullDocs'],
            'insuranceWay' => ['shortDocs','shortService','fullDocs'],
            'insuranceWayText' => ['shortDocs','shortService','fullDocs']
        ], // legal
        // data privacy:
        'dataPrivacy' => [
            'processing' => ['fullBegun','fullRequested','fullDocs'],
            'create' => ['shortDocs','shortService','fullDocs'],
            'verification' => ['fullDocs'],
            'confirmIntro' => ['shortDocs','shortService','fullDocs'],
            'responsibility' => ['shortDocs','shortService','fullDocs'],
            'transferOutside' => ['shortDocs','shortService','fullDocs'],
            'dataOnline' => ['shortDocs','shortService','fullDocs'],
            'dataOnlineProcessing' => ['shortDocs','shortService','fullDocs'],
            'dataPersonal' => ['shortDocs','shortService','fullDocs'],
            'marking' => ['shortDocs','shortService','fullDocs'],
            'markingDescription' => ['shortDocs','shortService','fullDocs'],
            'external' => ['shortDocs','shortService','fullDocs'],
            'internal' => ['shortDocs','shortService','fullDocs'],
            'pattern' => ['shortDocs','shortService','fullDocs'],
            'own' => ['shortDocs','shortService','fullDocs'],
            'contributors' => ['shortDocs','shortService','fullDocs'],
            'markingFurther' => ['shortDocs','shortService','fullDocs'],
            'markingSecond' => ['shortDocs','shortService','fullDocs'],
            'markingSecondDescription' => ['shortDocs','shortService','fullDocs'],
            'externalSecond' => ['shortDocs','shortService','fullDocs'],
            'internalSecond' => ['shortDocs','shortService','fullDocs'],
            'patternSecond' => ['shortDocs','shortService','fullDocs'],
            'ownSecond' => ['shortDocs','shortService','fullDocs'],
            'contributorsSecond' => ['shortDocs','shortService','fullDocs'],
            'name' => ['shortDocs','shortService','fullDocs'],
            'eMail' => ['shortDocs','shortService','fullDocs'],
            'studentNumber' => ['shortDocs','shortService','fullDocs'],
            'token' => ['shortDocs','shortService','fullDocs'],
            'sona' => ['shortDocs','shortService','fullDocs'],
            'prolific' => ['shortDocs','shortService','fullDocs'],
            'listIP' => ['shortDocs','shortService','fullDocs'],
            'listOther' => ['shortDocs','shortService','fullDocs'],
            'listOtherText' => ['shortDocs','shortService','fullDocs'],
            'demographic' => ['shortDocs','shortService','fullDocs'],
            'demographicText' => ['shortDocs','shortService','fullDocs'],
            'observation' => ['shortDocs','shortService','fullDocs'],
            'observationText' => ['shortDocs','shortService','fullDocs'],
            'survey' => ['shortDocs','shortService','fullDocs'],
            'surveyText' => ['shortDocs','shortService','fullDocs'],
            'audio' => ['shortDocs','shortService','fullDocs'],
            'photo' => ['shortDocs','shortService','fullDocs'],
            'video' => ['shortDocs','shortService','fullDocs'],
            'instrumental' => ['shortDocs','shortService','fullDocs'],
            'instrumentalText' => ['shortDocs','shortService','fullDocs'],
            'ip' => ['shortDocs','shortService','fullDocs'],
            'dataResearchOther' => ['shortDocs','shortService','fullDocs'],
            'dataResearchOtherText' => ['shortDocs','shortService','fullDocs'],
            'ethnic' => ['shortDocs','shortService','fullDocs'],
            'political' => ['shortDocs','shortService','fullDocs'],
            'union' => ['shortDocs','shortService','fullDocs'],
            'sexual' => ['shortDocs','shortService','fullDocs'],
            'brainStructure' => ['shortDocs','shortService','fullDocs'],
            'biometric' => ['shortDocs','shortService','fullDocs'],
            'biometricText' => ['shortDocs','shortService','fullDocs'],
            'health' => ['shortDocs','shortService','fullDocs'],
            'healthText' => ['shortDocs','shortService','fullDocs'],
            'genetic' => ['shortDocs','shortService','fullDocs'],
            'hair' => ['shortDocs','shortService','fullDocs'],
            'saliva' => ['shortDocs','shortService','fullDocs'],
            'bloodSample' => ['shortDocs','shortService','fullDocs'],
            'dataResearchSpecialOther' => ['shortDocs','shortService','fullDocs'],
            'dataResearchSpecialOtherText' => ['shortDocs','shortService','fullDocs'],
            'grouping' => ['shortDocs','shortService','fullDocs'],
            'convert' => ['shortDocs','shortService','fullDocs'],
            'delete' => ['shortDocs','shortService','fullDocs'],
            'alienate' => ['shortDocs','shortService','fullDocs'],
            'preprocess' => ['shortDocs','shortService','fullDocs'],
            'anonymizationOther' => ['shortDocs','shortService','fullDocs'],
            'anonymizationOtherText' => ['shortDocs','shortService','fullDocs'],
            'anonymizationNo' => ['shortDocs','shortService','fullDocs'],
            'storage' => ['shortDocs','shortService','fullDocs'],
            'storageDescription' => ['shortDocs','shortService','fullDocs'],
            'documentation' => ['shortDocs','shortService','fullDocs'],
            'documentationText' => ['shortDocs','shortService','fullDocs'],
            'reuse' => ['shortDocs','shortService','fullDocs'],
            'reuseText' => ['shortDocs','shortService','fullDocs'],
            'teaching' => ['shortDocs','shortService','fullDocs'],
            'teachingText' => ['shortDocs','shortService','fullDocs'],
            'demonstration' => ['shortDocs','shortService','fullDocs'],
            'demonstrationText' => ['shortDocs','shortService','fullDocs'],
            'documentationpersonalKeepConsent' => ['shortDocs','shortService','fullDocs'],
            'reusepersonalKeepConsent' => ['shortDocs','shortService','fullDocs'],
            'teachingpersonalKeepConsent' => ['shortDocs','shortService','fullDocs'],
            'demonstrationpersonalKeepConsent' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributors' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalinstitution' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'dataPersonaldataService' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalcontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'dataPersonalaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'dataPersonaldataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'dataPersonaldataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'purposeNo' => ['shortDocs','shortService','fullDocs'],
            'compensation' => ['shortDocs','shortService','fullDocs'],
            'relatable' => ['shortDocs','shortService','fullDocs'],
            'contact' => ['shortDocs','shortService','fullDocs'],
            'technical' => ['shortDocs','shortService','fullDocs'],
            'relatableContactResult' => ['shortDocs','shortService','fullDocs'],
            'relatableFeedback' => ['shortDocs','shortService','fullDocs'],
            'relatableDeletion' => ['shortDocs','shortService','fullDocs'],
            'relatableLinking' => ['shortDocs','shortService','fullDocs'],
            'purposeFurtherpurposeNo' => ['shortDocs','shortService','fullDocs'],
            'purposeFurthercompensation' => ['shortDocs','shortService','fullDocs'],
            'purposeFurthercontact' => ['shortDocs','shortService','fullDocs'],
            'purposeFurthercontactResult' => ['shortDocs','shortService','fullDocs'],
            'purposeFurthertechnical' => ['shortDocs','shortService','fullDocs'],
            'compensationname' => ['shortDocs','shortService','fullDocs'],
            'compensationeMail' => ['shortDocs','shortService','fullDocs'],
            'compensationphone' => ['shortDocs','shortService','fullDocs'],
            'compensationaddress' => ['shortDocs','shortService','fullDocs'],
            'compensationstudentNumber' => ['shortDocs','shortService','fullDocs'],
            'compensationtoken' => ['shortDocs','shortService','fullDocs'],
            'compensationsona' => ['shortDocs','shortService','fullDocs'],
            'compensationprolific' => ['shortDocs','shortService','fullDocs'],
            'compensationiban' => ['shortDocs','shortService','fullDocs'],
            'compensationpurposeDataOther' => ['shortDocs','shortService','fullDocs'],
            'compensationpurposeDataOtherText' => ['shortDocs','shortService','fullDocs'],
            'compensationmarkingRemove' => ['shortDocs','shortService','fullDocs'],
            'compensationmarkingRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'compensationmiddleList' => ['shortDocs','shortService','fullDocs'],
            'compensationmiddleCodeChange' => ['shortDocs','shortService','fullDocs'],
            'compensationmiddleCodeRemove' => ['shortDocs','shortService','fullDocs'],
            'compensationmiddleName' => ['shortDocs','shortService','fullDocs'],
            'compensationlaterDescription' => ['shortDocs','shortService','fullDocs'],
            'compensationpersonalRemove' => ['shortDocs','shortService','fullDocs'],
            'compensationpersonalRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'compensationkeepDescription' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributors' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'compensationinstitution' => ['shortDocs','shortService','fullDocs'],
            'compensationinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'compensationaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'compensationdataService' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'compensationcontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'compensationaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'compensationaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'compensationdataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'compensationdataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'relatablename' => ['shortDocs','shortService','fullDocs'],
            'relatableeMail' => ['shortDocs','shortService','fullDocs'],
            'relatablephone' => ['shortDocs','shortService','fullDocs'],
            'relatableaddress' => ['shortDocs','shortService','fullDocs'],
            'relatablestudentNumber' => ['shortDocs','shortService','fullDocs'],
            'relatabletoken' => ['shortDocs','shortService','fullDocs'],
            'relatablesona' => ['shortDocs','shortService','fullDocs'],
            'relatableprolific' => ['shortDocs','shortService','fullDocs'],
            'relatableiban' => ['shortDocs','shortService','fullDocs'],
            'relatablepurposeDataOther' => ['shortDocs','shortService','fullDocs'],
            'relatablepurposeDataOtherText' => ['shortDocs','shortService','fullDocs'],
            'relatablemarkingRemove' => ['shortDocs','shortService','fullDocs'],
            'relatablemarkingRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'relatablemiddleList' => ['shortDocs','shortService','fullDocs'],
            'relatablemiddleCodeChange' => ['shortDocs','shortService','fullDocs'],
            'relatablemiddleCodeRemove' => ['shortDocs','shortService','fullDocs'],
            'relatablemiddleName' => ['shortDocs','shortService','fullDocs'],
            'relatablelaterDescription' => ['shortDocs','shortService','fullDocs'],
            'relatablepersonalRemove' => ['shortDocs','shortService','fullDocs'],
            'relatablepersonalRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'relatablekeepDescription' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributors' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'relatableinstitution' => ['shortDocs','shortService','fullDocs'],
            'relatableinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'relatableaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'relatabledataService' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'relatablecontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'relatableaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'relatableaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'relatabledataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'relatabledataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactname' => ['shortDocs','shortService','fullDocs'],
            'contacteMail' => ['shortDocs','shortService','fullDocs'],
            'contactphone' => ['shortDocs','shortService','fullDocs'],
            'contactaddress' => ['shortDocs','shortService','fullDocs'],
            'contactstudentNumber' => ['shortDocs','shortService','fullDocs'],
            'contacttoken' => ['shortDocs','shortService','fullDocs'],
            'contactsona' => ['shortDocs','shortService','fullDocs'],
            'contactprolific' => ['shortDocs','shortService','fullDocs'],
            'contactiban' => ['shortDocs','shortService','fullDocs'],
            'contactpurposeDataOther' => ['shortDocs','shortService','fullDocs'],
            'contactpurposeDataOtherText' => ['shortDocs','shortService','fullDocs'],
            'contactmarkingRemove' => ['shortDocs','shortService','fullDocs'],
            'contactmarkingRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'contactmiddleList' => ['shortDocs','shortService','fullDocs'],
            'contactmiddleCodeChange' => ['shortDocs','shortService','fullDocs'],
            'contactmiddleCodeRemove' => ['shortDocs','shortService','fullDocs'],
            'contactmiddleName' => ['shortDocs','shortService','fullDocs'],
            'contactlaterDescription' => ['shortDocs','shortService','fullDocs'],
            'contactpersonalRemove' => ['shortDocs','shortService','fullDocs'],
            'contactpersonalRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'contactkeepDescription' => ['shortDocs','shortService','fullDocs'],
            'contactcontributors' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'contactinstitution' => ['shortDocs','shortService','fullDocs'],
            'contactinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'contactaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'contactdataService' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'contactcontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactdataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactdataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactResultname' => ['shortDocs','shortService','fullDocs'],
            'contactResulteMail' => ['shortDocs','shortService','fullDocs'],
            'contactResultphone' => ['shortDocs','shortService','fullDocs'],
            'contactResultaddress' => ['shortDocs','shortService','fullDocs'],
            'contactResultstudentNumber' => ['shortDocs','shortService','fullDocs'],
            'contactResulttoken' => ['shortDocs','shortService','fullDocs'],
            'contactResultsona' => ['shortDocs','shortService','fullDocs'],
            'contactResultprolific' => ['shortDocs','shortService','fullDocs'],
            'contactResultiban' => ['shortDocs','shortService','fullDocs'],
            'contactResultpurposeDataOther' => ['shortDocs','shortService','fullDocs'],
            'contactResultpurposeDataOtherText' => ['shortDocs','shortService','fullDocs'],
            'contactResultmarkingRemove' => ['shortDocs','shortService','fullDocs'],
            'contactResultmarkingRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'contactResultmiddleList' => ['shortDocs','shortService','fullDocs'],
            'contactResultmiddleCodeChange' => ['shortDocs','shortService','fullDocs'],
            'contactResultmiddleCodeRemove' => ['shortDocs','shortService','fullDocs'],
            'contactResultmiddleName' => ['shortDocs','shortService','fullDocs'],
            'contactResultlaterDescription' => ['shortDocs','shortService','fullDocs'],
            'contactResultpersonalRemove' => ['shortDocs','shortService','fullDocs'],
            'contactResultpersonalRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'contactResultkeepDescription' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributors' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'contactResultinstitution' => ['shortDocs','shortService','fullDocs'],
            'contactResultinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'contactResultaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'contactResultdataService' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'contactResultcontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactResultaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactResultaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'contactResultdataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'contactResultdataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'technicalmarkingRemove' => ['shortDocs','shortService','fullDocs'],
            'technicalmarkingRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'technicalmiddleList' => ['shortDocs','shortService','fullDocs'],
            'technicalmiddleCodeChange' => ['shortDocs','shortService','fullDocs'],
            'technicalmiddleCodeRemove' => ['shortDocs','shortService','fullDocs'],
            'technicalmiddleName' => ['shortDocs','shortService','fullDocs'],
            'technicallaterDescription' => ['shortDocs','shortService','fullDocs'],
            'technicalpersonalRemove' => ['shortDocs','shortService','fullDocs'],
            'technicalpersonalRemoveDescription' => ['shortDocs','shortService','fullDocs'],
            'technicalkeepDescription' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributors' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsPart' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsPartText' => ['shortDocs','shortService','fullDocs'],
            'technicalinstitution' => ['shortDocs','shortService','fullDocs'],
            'technicalinstitutionText' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsOther' => ['shortDocs','shortService','fullDocs'],
            'technicalaccessExternal' => ['shortDocs','shortService','fullDocs'],
            'technicaldataService' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsOtherorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsOtherorderProcessingDescription' => ['shortDocs','shortService','fullDocs'],
            'technicalcontributorsOtherorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'technicalaccessExternalorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'technicalaccessExternalorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'technicaldataServiceorderProcessing' => ['shortDocs','shortService','fullDocs'],
            'technicaldataServiceorderProcessingKnown' => ['shortDocs','shortService','fullDocs'],
            'orderProcessingStart' => ['shortDocs','shortService','fullDocs'],
            'orderProcessingMiddle' => ['shortDocs','shortService','fullDocs'],
            'orderProcessingEnd' => ['shortDocs','shortService','fullDocs'],
            'codeCompensation' => ['shortDocs','shortService','fullDocs'],
            'codeCompensationDescription' => ['shortDocs','shortService','fullDocs'],
            'codeInternal' => ['shortDocs','shortService','fullDocs'],
            'codeCompensationexternal' => ['shortDocs','shortService','fullDocs'],
            'codeCompensationpattern' => ['shortDocs','shortService','fullDocs'],
            'codeCompensationcontributors' => ['shortDocs','shortService','fullDocs'],
            'processingFurther' => ['shortDocs','shortService','fullDocs']
        ], // data privacy
        // data reuse
        'dataReuse' => [
            'confirmIntro' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'dataReuse' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
            'dataReuseHow' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseHowDescription' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseHowreuse' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseHowreuseDescription' => ['shortDocs','shortService','fullBegun','fullDocs'],
            'dataReuseSelf' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs']
        ], // data reuse
        // no array for contributor because the IDs of the Form elements contain the contributors IDs
        // no array for complete form because the IDs of the Form elements may contain study etc. ID
    ]; // formTypeQuestions

    /** Each key is one page. Each value is an array indicating the application types for which the page may be active. Contains only the projectdetails pages. */
    protected const reviewTypePages = [
        'groups' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'information' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'informationII' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'consent' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'measures' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'burdensRisks' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'compensation' => ['shortNoDocs','shortDocs','shortService','shortBegun','shortRequested','fullBegun','fullRequested','fullDocs'],
        'texts' => ['shortDocs','shortService','fullDocs'],
        'informationIII' => ['shortDocs','shortService','fullDocs'],
        'legal' => ['shortDocs','shortService','fullDocs'],
        'dataPrivacy' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
        'dataReuse' => ['shortDocs','shortService','fullBegun','fullRequested','fullDocs'],
        'contributor' => ['shortDocs','shortService','fullDocs']
    ]; // reviewTypePages

    /** Each key is a pdf that may have to be added if the complete proposal is created. Each value is an array indicating the application types for which the pdf may have to be added. Only PDFs for projectdetails are listed here, i.e., not the votes pdf.
     */
    protected const reviewTypesPDF = [ // keys must equal the values in ControllerAbstract::customPDForder
        'dataPrivacy' => ['shortDocs','fullDocs'],
        'begun' => ['shortBegun','fullBegun'],
        'information' => ['fullDocs'],
        'informationII' => ['fullBegun','fullDocs'],
        'measures' => ['fullBegun','fullRequested','fullDocs'],
        'interventions' => ['fullBegun','fullRequested','fullDocs'],
        'otherSources' => ['fullBegun','fullRequested','fullDocs']
    ];

    /** Each key in one page. Each value is an array. The keys are the keys from the sub-arrays of $reviewQuestions. The values indicate which nodes must have which values for the node (the key) to be created in the xml-file. The values are arrays with the following structure:
     * 0) array of nodes representing a path to a specific node. The first value must be a page node of projectdetails.
     * 1) value the node is checked for or a boolean. If a boolean, then the node is checked for children.
     * 2) children that are added if the node is created.
     * 0) and 1) may also be arrays of arrays. In that case, the arrays in 0) must have the same size as the arrays in 1). The node is only created if all checks return true. If the value is an array, the node must have either of the values.
     * 3) If true and 0) and 1) are arrays of arrays, then the node is created if one of the checks returns true.
     * Keys that are not existent in the array are always created in case they are needed to the respective application type. If a node has children, but no dependencies when to be created, the first two elements are empty. Nodes that are created for every application type and do not have any dependencies are not listed.
     *
     * Example 1: [moneyDescription => [[compensation,type,money],'',[description,additional]]]. If the 'money'-node is existent and has an empty value, the node 'moneyDescription' is created with the children 'description' and 'additional'.
     *
     * Example 2: [node => [[[node1,node2],[node3,node4]],[value1,[value2,value3]],[new1,new2]]]: If node1 has the value value1 and node2 either the value value2 or value3, then 'node' is created with children 'new1' and 'new2'.
     */
    protected const pageDependencies = [
        // groups
        'groups' => [
            'peopleDescription' => [[['groups','examinedPeople','physical'],['groups','examinedPeople','mental'],['groups','examinedPeople','medical'],['groups','examinedPeople','institutional'],['groups','examinedPeople','wards'],['groups','examinedPeople','vulnerable'],['groups','examinedPeople','dependent'],['groups','examinedPeople','otherPeople']],['','','','','','','',''],[],true],
            'include' => [[],[],['noCriteria','criteria']],
            'exclude' => [[],[],['noCriteria','criteria']],
            'criteria' => [[],[],['include','exclude']],
            'sampleSize' => [[],[],['total','furtherParticulars','sampleSizePlan']]
        ], // groups
        // information
        'information' => [
            'preType' => [['information','pre'],'0'],
            'preContent' => [['information','pre'],'0'],
            'preComplete' => [[['information','pre'],['information','preContent']],['0',['partial','deceit']],'chosen'],
            'preText' => [['information','pre'],'1'],
            'post' => [['information','pre'],'1','chosen'],
            'attendance' => [['groups','examinedPeople','wards'],''],
            'documentTranslation' => [[['information','pre'],['information','post','chosen']],['0','0'],'chosen',true],
            'documentTranslationPDF' => [['dummy'],''], // dummy node name to avoid creating the node (gets only created if checkbox is checked)
        ], // information
        // consent
        'consent' => [
            'participation' => [[['information','pre'],['information','post','chosen']],['0','0'],[],true], // may be removed again if terminate cons is not answered with 'no'
            'terminateParticipants' => [[],[],'chosen']
        ], // consent
        // measures
        'measures' => [
            'measuresPDF' => [['dummy'],''], // dummy node name to avoid creating the node (gets only created if checkbox is checked)
            'interventionsDescription' => [[['measures','interventions','feedback'],['measures','interventions','everyday'],['measures','interventions','stimulus'],['measures','interventions','tasks'],['measures','interventions','stimulation'],['measures','interventions','psychological'],['measures','interventions','physical'],['measures','interventions','therapy'],['measures','interventions','medical'],['measures','interventions','invasive'],['measures','interventions','other']],['','','','','','','','','','',''],[],true],
            'interventionsPDF' => [['dummy'],''], // dummy node name to avoid creating the node (gets only created if checkbox is checked)
            'loan' => [[],[],'chosen'],
            'location' => [[],[],'chosen']
        ], // measures
        // burdensRisks
        'burdensRisks' => [
            'burdensNoDescription' => [['burdensRisks','burdens','burdensType','noBurdens'],''],
            'feedback' => [[],[],'chosen']
        ], // burdensRisks
        // compensation
        'compensation' => [
            'moneyDescription' => [['compensation','type','money'],'',['description','additional']],
            'moneyFurther' => [['compensation','type','money'],'','chosen'],
            'moneyawarding' => [['compensation','type','money'],'','chosen'],
            'hoursDescription' => [['compensation','type','hours'],'',['description','additional']],
            'hoursawarding' => [['compensation','type','hours'],'','chosen'],
            'lotteryDescription' => [['compensation','type','lottery'],'','description'],
            'lotteryawarding' => [['compensation','type','lottery'],'',['chosen','lotteryStartDescription','lotteryStart']],
            'voucherDescription' => [['compensation','type','voucher'],'','description'],
            'voucherawarding' => [['compensation','type','voucher'],'','chosen'],
            'compensationOtherDescription' => [['compensation','type','compensationOther'],'','description'],
            'compensationOtherawarding' => [['compensation','type','compensationOther'],'','chosen'],
            'terminate' => [[['compensation','type','money'],['compenation','type','hours'],['compensation','type','lottery'],['compensation','type','voucher'],['compensation','type','compensationOther']],['','','','',''],'chosen',true],
            'compensationVoluntary' => [[['compensation','type','money'],['compenation','type','hours'],['compensation','type','lottery'],['compensation','type','voucher'],['compensation','type','compensationOther']],['','','','',''],'chosen',true],
            'furtherDescription' => [[['compensation','type','money'],['compenation','type','hours'],['compensation','type','lottery'],['compensation','type','voucher'],['compensation','type','compensationOther']],['','','','',''],[],true]
        ], // compensation
        // texts
        'texts' => [
            'intro' => [[['information','pre'],['information','post','chosen']],['0','0'],['introTemplate','description'],true],
            'goals' => [[['information','pre'],['information','post','chosen']],['0','0'],[],true],
            'conflictText' => [['dummy'],''], // dummy to avoid creating the node
            'pro' => [[['information','pre'],['information','post','chosen']],['0','0'],['proTemplate','description'],true],
            'con' => [[['information','pre'],['information','post','chosen']],['0','0'],['conTemplate','description'],true],
            'findingText' => [[['information','pre'],['information','post','chosen']],['0','0'],[],true] // may be removed again if finding is not answered with 'yes'
        ], // texts
        // informationIII
        'informationIII' => [
            'goals' => [['information','preComplete','chosen'],['0']],
            'infoBefore' => [['information','preComplete','chosen'],['0']],
            'infoAfter' => [['information','preComplete','chosen'],['0']],
            'explain' => [['information','preComplete','chosen'],['0']]
        ],
        // legal
        'legal' => [ // only dummy nodes to avoid creating them
            'liability' => [['dummy'],''],
            'insurance' => [['dummy'],''],
            'apparatus' => [['dummy'],''],
            'insuranceWay' => [['dummy'],'']
        ],
        // data privacy
        'dataPrivacy' => [
            'create' => [[],[],'chosen'],
            'verification' => [['dataPrivacy','create','chosen'],'separate'],
            'responsibility' => [['dataPrivacy','create','description'],'1'],
            'transferOutside' => [['dataPrivacy','create','description'],'1'],
            'dataOnline' => [[['dataPrivacy','responsibility'],['dataPrivacy','transferOutside'],['measures','location','chosen']],[['onlyOwn','notApplicable'],['no','notApplicable'],'online'],'chosen'],
            'dataPersonal' => [[['dataPrivacy','responsibility'],['dataPrivacy','transferOutside']],[['onlyOwn','notApplicable'],['no','notApplicable']]],
            'marking' => [[['dataPrivacy','responsibility'],['dataPrivacy','transferOutside']],[['onlyOwn','notApplicable'],['no','notApplicable']],'chosen'],
            'markingSecond' => [['dataPrivacy','markingFurther'],'0','chosen'],
            'markingFurther' => [['dataPrivacy','marking','chosen'],['external','internal','name']],
            'list' => [[['dataPrivacy','marking','codePersonal'],['dataPrivacy','markingSecond','codePersonal']],['list','list'],[],true],
            'dataResearch' => [[['dataPrivacy','dataPersonal'],['dataPrivacy','dataOnline','description']],[['personal','personalMaybe'],'linked'],[],true], // all following nodes including this one may be removed again if marking is 'other' (except purposeResearch which was not created or already removed in that case)
            'anonymization' => [['dataPrivacy','dataPersonal'],['personal','personalMaybe']],
            'storage' => [[['dataPrivacy','anonymization','grouping'],['dataPrivacy','anonymization','convert'],['dataPrivacy','anonymization','delete'],['dataPrivacy','anonymization','alienate'],['dataPrivacy','anonymization','preprocess'],['dataPrivacy','anonymization','anonymizationOther']],['','','','','',''],'chosen',true],
            'personalKeep' => [[['dataPrivacy','anonymization','anonymizationNo'],['dataPrivacy','storage','chosen']],['','keep'],[],true],
            'access' => [['dataPrivacy','dataPersonal'],['personal','personalMaybe']],
            'purposeResearch' => [['dataPrivacy','marking','chosen'],['external','internal','name']],
            'purposeFurther' => [[['dataPrivacy','responsibility'],['dataPrivacy','transferOutside']],[['onlyOwn','notApplicable'],['no','notApplicable']]],
            'codeCompensation' => [[],[],'chosen'], // may be removed again
            'processingFurther' => [[['dataPrivacy','responsibility'],['dataPrivacy','transferOutside']],[['onlyOwn','notApplicable'],['no','notApplicable']]]
        ], // data privacy
        // data reuse
        'dataReuse' => [
            'dataReuse' => [['dataReuse','confirmIntro'],'1'],
            'dataReuseHow' => [['dataReuse','confirmIntro'],'1','chosen'],
            'dataReuseHowreuse' => [['dataReuse','confirmIntro'],'1','chosen'],
            'dataReuseSelf' => [['dataReuse','dataReuse'],'no']
        ] // data reuse
    ];

    // functions

    /** Gets the type of review process.
     * @param array|SimpleXMLElement $application array of xml-node containing all information about the application
     * @return string type of review process
     */
    protected function getCurrentReviewProcess(array|SimpleXMLElement $application): string
    {
        if (!is_array($application)) {
            $application = $this->xmlToArray($application);
        }
        $coreDataArray = $application[self::appDataNodeName][self::coreDataNode];
        $applicationProcessArray = $coreDataArray[self::applicationProcessNode];
        $appType = $applicationProcessArray[self::chosen]; // short or full
        $isRequested = false; // gets true if only requested funding exists
        $fundingArray = $coreDataArray[self::funding];
        if ($fundingArray!=='' && array_diff(array_keys($fundingArray),self::fundingResearchExternal)===[]) {
            $isRequested = true;
            foreach ($fundingArray as $funding) {
                $isRequested = $isRequested && $funding[self::fundingStateNode]===self::fundingRequested;
            }
        }
        $reviewProcess = '';
        if ($appType!=='') {
            $projectStartArray = $coreDataArray[self::projectStart];
            $reviewProcess = $projectStartArray[self::chosen]==='0' && array_key_exists(self::descriptionNode,$projectStartArray)
                ? $appType.'Begun'
                : ($isRequested
                    ? $appType.'Requested'
                    : ($appType===self::reviewProcessFull
                        ? self::reviewFullDocs
                        : (array_key_exists(self::shortDocsNode,$applicationProcessArray)
                            ? ($applicationProcessArray[self::shortDocsNode]==='0' ? self::reviewShortService : self::reviewShortNoDocs)
                            : self::reviewShortDocs)));
        }
        if ($reviewProcess==='') {
            $reviewProcess = $this->getReviewShortDefault($application[self::committee]);
        }
        return $reviewProcess;
    }

    /** Checks if the current review process may contain participation documents.
     * @param Session $session current session
     * @return bool true if the current review process may contain participation documents, false otherwise
     */
    protected function getReviewDocs(Session $session): bool
    {
        return in_array($session->get(self::reviewProcess),self::reviewDocs);
    }

    /** Gets the default short application type
     * @param string $committeeType committee type
     * @return string default short application type
     */
    protected function getReviewShortDefault(string $committeeType): string
    {
        return in_array($committeeType,self::reviewShortChoose) ? self::reviewShortNoDocs : self::reviewShortDocs;
    }

    /** Updates the nodes of measure time point
     * @param Request $request
     * @param SimpleXMLElement $measureTimePointNode node containing the information about the current measure time point
     * @param string $reviewProcess type of review process
     * @return void
     */
    protected function updateNodesByReviewProcess(Request $request, SimpleXMLElement $measureTimePointNode, string $reviewProcess): void
    {
        foreach (self::reviewQuestions as $page => $pageNodes) { // keys: page names, values: array of questions with review types
            $pageNode = $measureTimePointNode->{$page};
            if (!in_array($reviewProcess,self::reviewTypePages[$page])) { // no question is asked on the current page
                $this->removeAllChildNodes($pageNode);
            } else { // at least one question is asked on the current page
                $lastIndex = count($pageNodes)-1; // index of last question
                $pageNodeKeys = array_keys($pageNodes);
                $pageDependencies = self::pageDependencies[$page] ?? []; // keys: questions, values: array of dependencies when the question is asked and children that are added to the question
                foreach (array_reverse($pageNodes) as $node => $reviewTypes) { // keys: questions, values: review types
                    $isAsked = in_array($reviewProcess,$reviewTypes);
                    $hasElement = $this->checkElement($node,$pageNode);
                    if (!$isAsked && $hasElement) { // question was asked, but is not asked now -> remove node
                        $this->removeElement($node,$pageNode);
                    } elseif ($isAsked && !$hasElement) { // question was not asked, but is asked now -> add node
                        // check if any dependencies exist
                        $curDependencies = $pageDependencies[$node] ?? [];
                        $createNode = true;
                        if (($curDependencies[0] ?? [])!==[]) { // at least one dependency exists
                            $dependencies = $curDependencies[0];
                            $dependencies = is_array($dependencies[0]) ? $dependencies : [$dependencies];
                            $values = $curDependencies[1];
                            $values = is_array($values) ? $values : [$values];
                            $isOr = $curDependencies[3] ?? false; // true if any of the dependencies must be true
                            $createNode = !$isOr; // gets true if node should be created
                            foreach ($dependencies as $index => $dependency) {
                                $curNode = $measureTimePointNode->{$dependency[0]}; // page node of current dependency
                                $depIndex = 1;
                                $lastDepIndex = count($dependency)-1;
                                while ($depIndex<=$lastDepIndex && $curNode!==null) {
                                    $curNode = $curNode->{$dependency[$depIndex]};
                                    ++$depIndex;
                                }
                                $curValues = $values[$index];
                                $hasValue = $curNode!==null && $curNode->getName()!=='' && in_array((string) $curNode,is_array($curValues) ? $curValues : [$curValues]);
                                if ($isOr) {
                                    $createNode = $createNode || $hasValue;
                                } else {
                                    $createNode = $createNode && $hasValue;
                                }
                            }
                        }
                        if ($createNode) { // node should be created
                            $index = array_search($node,$pageNodeKeys); // index of question
                            $isNextAsked = false; // gets true for the first question following the current one that is also asked
                            while ($index<$lastIndex && !$isNextAsked) {
                                ++$index;
                                $nextKey = $pageNodeKeys[$index];
                                $isNextAsked = in_array($reviewProcess,$pageNodes[$nextKey]) && $this->checkElement($nextKey,$pageNode);
                            }
                            if ($index>=$lastIndex && !$isNextAsked) { // last question on page
                                $nodeAfter = $pageNode->addChild('dummy');
                            } else { // another question after the current one is also asked
                                $nodeAfter = $pageNode->{$pageNodeKeys[$index]};
                            }
                            $children = $curDependencies[2] ?? [];
                            $this->insertElementBefore($node,$nodeAfter,is_array($children) ? $children : [$children]);
                            $this->removeElement('dummy',$pageNode);
                        }
                    } // elseif (isAsked && !hasElement)
                } // foreach pageNodes
            } // else (at least one question asked)
        } // foreach reviewQuestions
        // remove nodes that depend on different inputs (e.g., A and [B or C]) -> They do (still) exist if they may exist for the review process
        $groupsNode = $measureTimePointNode->{self::groupsNode};
        $consentNode = $measureTimePointNode->{self::consentNode};
        $compensationNode = $measureTimePointNode->{self::compensationNode};
        if ($this->getShortBegunRequested($request)) { // if creation of participation document can be chosen, but data collection has already begun or funding is requested, intermediate page must have the same information as for shortService and shortNoDocs, i.e., some nodes that were created need to be removed again
            $this->removeElement(self::criteriaIncludeNode,$groupsNode);
            $this->removeElement(self::criteriaExcludeNode,$groupsNode);
            $this->removeElement(self::locationNode,$measureTimePointNode->{self::measuresNode});
            $this->removeElement(self::terminateParticipantsNode,$consentNode);
            $compensationTypesArray = $this->xmlToArray($compensationNode[0])[self::compensationTypeNode];
            if ($compensationTypesArray!=='' && !array_key_exists(self::compensationNo,$compensationTypesArray)) {
                foreach (array_keys($compensationTypesArray) as $compensation) {
                    $this->removeElement($compensation.self::descriptionCap,$compensationNode);
                    $this->removeElement($compensation.self::awardingNode,$compensationNode);
                    if ($compensation===self::compensationMoney) {
                        $this->removeElement(self::moneyFurther,$compensationNode);
                    }
                }
            }
        }
        if ($this->checkElement(self::criteriaIncludeNode,$groupsNode)) { // add (not remove) first inclusion criterion if old review process was shortNoDocs
            $include = $groupsNode->{self::criteriaIncludeNode}->{self::criteriaNode};
            $firstInclude = self::criteriaIncludeNode.'0';
            if (!$this->checkElement($firstInclude,$include)) {
                $include->addChild($firstInclude);
                $this->setFirstInclusion($groupsNode, $request->getLocale());
            }
        }
        $informationNode = $measureTimePointNode->{self::informationNode};
        $isPre = $this->checkElement(self::pre,$informationNode) && ((string) $informationNode->{self::pre})==='0';
        $legalNode = $measureTimePointNode->{self::legalNode}; // legal nodes are not changed if the review process changes
        $hasLegal = count($legalNode->children())>0;
        if (!$isPre) {
            // legal
            if ($hasLegal) {
                $this->removeAllChildNodes($legalNode);
            }
        } elseif (!$hasLegal && in_array($reviewProcess,self::reviewTypePages[self::legalNode])) {
            $this->addLegalNodes($legalNode,$this->xmlToArray($measureTimePointNode));
        }
        // nodes that exist if any information is given
        if ($isPre || $this->checkElement(self::post,$informationNode) && ((string) $informationNode->{self::post}->{self::chosen})==='0') {
            // document translation
            if (((string) $consentNode->{self::consent}->{self::chosen})===self::consentOral) {
                $this->removeElement(self::documentTranslationNode,$informationNode);
            }
            // finding text
            if (((string) $measureTimePointNode->{self::burdensRisksNode}->{self::findingNode}->{self::chosen})!=='0') {
                $this->removeElement(self::findingTextNode,$measureTimePointNode->{self::textsNode});
            }
            // terminate cons participation
            if (((string) $consentNode->{self::terminateConsNode}->{self::chosen})!=='1') {
                $this->removeElement(self::terminateConsParticipationNode,$consentNode);
            }
        }
        // data privacy
        $privacyNode = $measureTimePointNode->{self::privacyNode}[0];
        if ($this->checkElement(self::markingNode,$privacyNode) && ((string) $privacyNode->{self::markingNode}->{self::chosen})===self::markingOther) {
            foreach ([self::dataResearchNode,self::anonymizationNode,self::storageNode,self::personalKeepNode,self::accessNode,self::purposeFurtherNode,self::processingFurtherNode] as $nodeName) { // remove all nodes that may have been created, but not needed because marking is 'other'
                $this->removeElement($nodeName,$privacyNode);
            }
        }
        $compensationArray = $this->xmlToArray($compensationNode[0]);
        $selections = $compensationArray[self::compensationTypeNode] ?? '';
        $hasCode = false; // gets true if any awarding is by code
        if ($selections!=='' && !array_key_exists(self::compensationNo,$selections)) {
            foreach (array_keys($selections) as $selection) {
                $hasCode = $hasCode || ($compensationArray[$selection.self::awardingNode][self::laterTypesName] ?? '')==='code';
            }
        }
        if (!$hasCode || $this->checkElement(self::purposeResearchNode,$privacyNode) && $this->checkElement(self::purposeCompensation,$privacyNode->{self::purposeResearchNode})) { // purpose of marking is compensation or no code is used for awarding
            $this->removeElement(self::codeCompensationNode,$privacyNode);
        }
        // data reuse
        $dataReuseNode = $measureTimePointNode->{self::dataReuseNode};
        $personalParams = $this->getPrivacyReuse($this->xmlToArray($privacyNode));
        $isPersonalPurpose = ($personalParams['personal'] ?? 'noTool')==='purpose';
        $isAnonymized = $personalParams['isAnonymized'] ?? false;
        if ($isPersonalPurpose && !$isAnonymized) {
            $this->removeElement(self::dataReuseNode,$dataReuseNode);
        }
        if ($this->checkElement(self::dataReuseNode,$dataReuseNode) && !in_array((string) $dataReuseNode->{self::dataReuseNode},self::dataReuseTypesYes)) {
            $this->removeElement(self::dataReuseHowNode,$dataReuseNode);
        }
        if (!($isPersonalPurpose && $isAnonymized)) {
            $this->removeElement(self::dataReuseHowNode.'reuse',$dataReuseNode);
        }
    }

    /** Checks whether the review process is 'shortBegun' or 'shortRequested' and the committee allows to choose whether documents should be created for short proposals.
     * @param Request $request
     * @return bool true if the review process is 'shortBegun' or 'shortRequested' and committee allows to choose whether documents should be created for short proposals, false otherwise
     */
    protected function getShortBegunRequested(Request $request): bool
    {
        $session = $request->getSession();
        return in_array($this->getCommitteeType($session),self::reviewShortChoose) && in_array($session->get(self::reviewProcess),[self::reviewShortBegun,self::reviewShortRequested]);
    }
}
