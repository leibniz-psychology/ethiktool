<?php

namespace App\Form\AppData;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class VotesType extends TypeAbstract
{
    use AppDataTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $translationPrefix = 'votes.';
        // other vote
        $tempPrefix = $translationPrefix.'otherVote.';
        $this->addBinaryRadio($builder,self::otherVote,$tempPrefix.'title',textName: self::otherVoteText);
        $this->addRadioGroup($builder,self::otherVoteResult,self::otherVoteResultTypes,$tempPrefix.'result.title',self::otherVoteResultDescription);
        // own institution vote
        $tempPrefix = $translationPrefix.'instVote.';
        $this->addBinaryRadio($builder, self::instVote, $tempPrefix.'title',self::instVoteText,$tempPrefix.self::textHint,self::instReference,$tempPrefix.self::instReference,[self::labelParams => $options[self::committeeParams]]);
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void
    {
        $forms = iterator_to_array($forms);
        // other vote
        $this->setChosenArray($forms,$viewData,self::otherVote,[self::otherVoteText,self::otherVoteResult,self::otherVoteResultDescription],false);
        // own institution vote
        $this->setChosenArray($forms,$viewData,self::instVote,[self::instReference,self::instVoteText],false);
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void
    {
        $forms = iterator_to_array($forms);
        // other vote
        $viewData[self::otherVote] = $this->getChosenArray($forms,self::otherVote,0,[self::otherVoteText,self::otherVoteResult,self::otherVoteResultDescription],false);
        // own institution Vote
        $viewData[self::instVote] = $this->getChosenArray($forms,self::instVote,0,[self::instReference,self::instVoteText],false);
    }
}