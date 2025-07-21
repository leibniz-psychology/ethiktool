<?php

namespace App\Form\AppData;

use App\Abstract\TypeAbstract;
use App\Traits\AppData\AppDataTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Traversable;

class VotesType extends TypeAbstract
{
    use AppDataTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $translationPrefix = 'votes.';
        // other vote
        $tempPrefix = $translationPrefix.'otherVote.';
        $this->addBinaryRadio($builder,self::otherVote,$tempPrefix.'title');
        $this->addFormElement($builder,self::otherVoteText,'text',$tempPrefix.'committee');
        $this->addRadioGroup($builder,self::otherVoteResult,self::otherVoteResultTypes,$tempPrefix.'result.title');
        $this->addFormElement($builder,self::otherVoteResultDescription,'textarea');
        // own institution vote
        $tempPrefix = $translationPrefix.'instVote.';
        $this->addBinaryRadio($builder, self::instVote, $tempPrefix.'title',self::instVoteText,$tempPrefix.self::textHint,[self::labelParams => $options[self::committeeParams]]);
        $this->addFormElement($builder, self::instReference,'text',hint: $tempPrefix.'reference');
        $this->addDummyForms($builder);
        $builder->setDataMapper($this);
    }

    public function mapDataToForms(mixed $viewData, Traversable $forms): void {
        $forms = iterator_to_array($forms);
        // other vote
        $this->setChosenArray($forms,$viewData,self::otherVote,[self::otherVoteText,self::otherVoteResult,self::otherVoteResultDescription]);
        // own institution vote
        $tempArray = $viewData[self::instVote];
        $forms[self::instVote]->setData($this->getArrayValue($tempArray, self::chosen));
        $forms[self::instReference]->setData($this->getArrayValue($tempArray, self::instReference));
        $forms[self::instVoteText]->setData($this->getArrayValue($tempArray, self::instVoteText));
    }

    public function mapFormsToData(Traversable $forms, mixed &$viewData): void {
        $forms = iterator_to_array($forms);
        // other vote
        $viewData[self::otherVote] = $this->getChosenArray($forms,self::otherVote,0,[self::otherVoteText,self::otherVoteResult,self::otherVoteResultDescription]);
        // own institution Vote
        $tempVal = $forms[self::instVote]->getData();
        $tempArray = [self::chosen => $tempVal];
        if ($tempVal===0) {
            $tempArray[self::instReference] = $this->getFormData($forms,self::instReference,$viewData[self::instVote][self::instReference] ?? '');
            $tempArray[self::instVoteText] = $forms[self::instVoteText]->getData();
        }
        $viewData[self::instVote] = $tempArray;
    }
}