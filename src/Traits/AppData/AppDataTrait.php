<?php

namespace App\Traits\AppData;

use App\Traits\PageTrait;

/** Contains all constants that are needed for the application data pages, mainly the names for the form widgets and nodes. */
trait AppDataTrait
{
    use PageTrait;

    protected const routePrefixAppData = 'appData/';
    // core data
    protected const coreDataNode = 'coreData';
    protected const applicationType = 'appType';
    protected const projectTitleParticipation = 'projectTitleParticipation';
    protected const projectTitleTypes = ['coreData.projectTitleParticipation.types.same' => 'same', 'coreData.projectTitleParticipation.types.different' => 'different', 'coreData.projectTitleParticipation.types.notApplicable' => 'notApplicable'];
    protected const projectTitleNotApplicable = 'notApplicable'; // must equal one value in $projectTitleTypes
    protected const projectTitleDifferent = 'different'; // must equal one of the values in $projectTitleTypes
    protected const qualification = 'qualification';
    protected const applicant = 'applicant';
    protected const projectStart = 'projectStart';
    protected const projectEnd = 'projectEnd';
    protected const funding = 'funding';
    protected const projectStartNext = 'projectStartNext';
    protected const projectStartBegun = 'projectStartBegun';
    protected const projectStartBegunConfirm = 'projectStartBegunConfirm';
    protected const projectStartRetrospective = 'retrospective';
    protected const begunCommittees = ['EUB','DLR','TUD','UH1','BICC','PHT','BTU']; // committees where review after start of the research project is possible
    protected const begunConfirmCommittees = ['EUB','DLR','BICC']; // committees where contact to the committee for already started projects is required
    protected const retrospectiveCommittees = ['TUD','UH1','PHT','BTU']; // committees where justification is needed why the research project has already started
    protected const begunIncompleteCommittees = ['EUB','BICC']; // committees where review after start is possible, but project must not have been completed
    protected const appNew = 'new';
    protected const applicationTypes = ['coreData.appType.type.new' => 'new', 'coreData.appType.type.extended' => 'extended', 'coreData.appType.type.resubmission' => 'resubmission', 'coreData.appType.type.resubmissionGranted' => 'resubmissionGranted']; // values must equal the values of the preceding variables
    protected const appExtendedResubmission = ['extended','resubmission','resubmissionGranted']; // values must equal the values of $appExtended and $appResubmission
    protected const appTypeExtended = 'appTypeExtended';
    protected const appTypeExReTypes = ['coreData.appTypeExtended.types.extendedSame' => 'extendedSame', 'coreData.appTypeExtended.types.extendedDifferent' => 'extendedDifferent'];
    protected const applicationProcessNode = 'applicationProcess';
    protected const applicationProcessTypes = ['coreData.applicationProcess.types.short' => 'short', 'coreData.applicationProcess.types.full' => 'full']; // values must equal the value of $reviewProcessShort and $reviewProcessFull in PageTrait
    protected const shortDocsNode = 'shortDocs';
    protected const shortDocsTypes = ['coreData.shortDocs.types.yes' => 'yes', 'coreData.shortDocs.types.no' => 'no', 'coreData.shortDocs.types.notApplicable' => 'notApplicable'];
    protected const shortDocsYes = 'yes'; // must equal one value in $shortDocsTypes
    protected const shortDocsNotApplicable = 'notApplicable'; // must equal one value in $shortDocsTypes
    protected const supervisor = 'supervisor';
    protected const committeeStudent = ['EUB','JGU','DLR','UH1','PHHD','SRH','BICC']; // committees where position can be 'student'
    protected const committeeSupervisor = ['EUB','JGU','UH1','PHHD','SRH','BICC']; // committees where a supervisor must be added if the position is student. Values must equal the values of $committeeStudent
    protected const committeeSupervisorPhD = ['EUB','PHHD','SRH']; // committees where a supervisor must be added if the position is PhD
    protected const committeePhDnoLeaderData = ['PHHD','SRH']; // committee where tasks 'leader' or 'data' can not be selected if the position is PhD
    protected const committeeNoStudent = ['PHHD','SRH']; // committees where the position can be student, but students cannot submit an application, i.e., 'complete form' is always disabled
    protected const fundingQuali = 'fundingQuali'; // must equal one of the keys in $fundingChoices
    protected const fundingResearchExternal = ['fundingResearch','fundingExternal']; // must equal two keys in $fundingChoices
    protected const fundingTypes = ['fundingQuali' => 'coreData.funding.fundingQuali', 'fundingBudget' => 'coreData.funding.fundingBudget', 'fundingResearch' => 'coreData.funding.fundingResearch', 'fundingExternal' => 'coreData.funding.fundingExternal', 'fundingOther' => 'coreData.funding.fundingOther'];
    protected const fundingStateNode = 'fundingState';
    protected const fundingRequested = 'requested';
    protected const requestedConfirm = 'requestedConfirm';
    protected const studentConfirm = 'studentConfirm';
    protected const conflictNode = 'conflict';
    protected const supportNode = 'support';
    protected const noSupport = 'noSupport'; // must equal one of the keys in $supportChoices
    protected const supportCenter = 'supportCenter'; // must equal one of the keys in $supportChoices
    protected const supportCommittee = 'supportCommittee'; // must equal one of the keys in $supportChoices
    protected const supportTypes = ['noSupport' => 'coreData.support.type.noSupport', 'supportCommittee' => 'coreData.support.type.supportCommittee', 'supportCenter' => 'coreData.support.type.supportCenter', 'supportData' => 'coreData.support.type.supportData', 'supportOther' => 'coreData.support.type.supportOther'];
    protected const guidelinesNode = 'guidelines';
    // votes
    protected const voteNode = 'votes';
    protected const otherVote = 'otherVote';
    protected const otherVoteText = 'description';
    protected const otherVoteResult = 'otherVoteResult';
    protected const otherVoteResultTypes = ['votes.otherVote.result.types.positive' => 'positive', 'votes.otherVote.result.types.negative' => 'negative', 'votes.otherVote.result.types.noVote' => 'noVote'];
    protected const otherVoteResultNegative = 'negative'; // must equal one of the keys in $otherVoteResultTypes
    protected const otherVoteResultDescription = 'otherVoteResultDescription';
    protected const instVote = 'instVote';
    protected const instReference = 'reference';
    protected const instVoteText = 'instVoteText';
    // medicine
    protected const medicine = 'medicine';
    protected const physicianNode = 'physician';
    // summary
    protected const summary = 'summary'; // node name and id of the widget
}
