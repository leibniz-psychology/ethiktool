<?php

namespace App\Traits;

trait LandingTrait
{
    use PageTrait; // for dummyParams in LandingType

    protected const isStudy = 'isStudy';
    protected const isGroup = 'isGroup';
    protected const isMeasure = 'isMeasure';
    protected const copy = 'copy';
    protected const newStudyGroupName = 'newStudyGroupText';
    protected const dropdownChoices = 'dropdownChoices'; // key in the $options array passed to buildForm()
    protected const isProjectdetails = 'isProjectdetails';
    protected const remove = 'remove';
    protected const edit = 'edit';
    protected const numStudies = 'numStudies';
    protected const numGroups = 'numGroups';
    protected const editName = 'editText'; // prefix for the IDs of the text fields for editing the group name.
}