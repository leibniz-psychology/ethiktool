<?php

namespace App\Traits\AppData;

use App\Traits\PageTrait;

/** Contains all constants that are needed for the application data pages, mainly the names for the form widgets and nodes. */
trait AppDataTrait
{
    use PageTrait;

    // core data
    protected const coreDataNode = 'coreData';
    protected const applicationType = 'appType';
    protected const projectTitleParticipation = 'projectTitleParticipation';
    protected const projectTitleTypes = ['coreData.projectTitleParticipation.types.same' => 'same', 'coreData.projectTitleParticipation.types.different' => 'different', 'coreData.projectTitleParticipation.types.notApplicable' => 'notApplicable'];
    protected const projectTitleNotApplicable = 'notApplicable'; // must equal one value in $projectTitleTypes
    protected const projectTitleDifferent = 'different'; // must equal one of the values in $projectTitleTypes
    protected const qualification = 'qualification';
    protected const applicationNewType = 'appNewType';
    protected const applicant = 'applicant';
    protected const projectStart = 'projectStart';
    protected const projectEnd = 'projectEnd';
    protected const funding = 'funding';
    protected const projectStartNext = 'projectStartNext';
    protected const projectStartBegun = 'projectStartBegun';
    protected const appNew = 'new';
    protected const appExtended = 'extended';
    protected const appResubmission = 'resubmission';
    protected const applicationTypes = ['coreData.appType.new.title' => 'new', 'coreData.appType.extended' => 'extended', 'coreData.appType.resubmission' => 'resubmission']; // values must equal the values of the preceding variables
    protected const appTypeShort = 'short';
    protected const supervisor = 'supervisor';
    protected const committeeStudent = ['EUB','JGU']; // committees where position can be 'student' and a supervisor must be added if student is selected
    protected const fundingQuali = 'fundingQuali'; // must equal one of the keys in $fundingChoices
    protected const fundingResearchExternal = ['fundingResearch','fundingExternal']; // must equal two keys in $fundingChoices
    protected const fundingTypes = ['fundingQuali' => 'coreData.funding.fundingQuali', 'fundingBudget' => 'coreData.funding.fundingBudget', 'fundingResearch' => 'coreData.funding.fundingResearch', 'fundingExternal' => 'coreData.funding.fundingExternal', 'fundingOther' => 'coreData.funding.fundingOther'];
    protected const fundingStateNode = 'fundingState';
    protected const conflictNode = 'conflict';
    protected const participantDescription = 'participantDescription';
    protected const supportNode = 'support';
    protected const noSupport = 'noSupport'; // must equal one of the keys in $supportChoices
    protected const supportCenter = 'supportCenter'; // must equal one of the keys in $supportChoices
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