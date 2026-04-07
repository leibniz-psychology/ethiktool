<?php

namespace App\Traits;

trait LandingTrait
{
    use PageTrait; // for dummyParams in LandingType

    protected const isMeasure = 'isMeasure';
    protected const newElement = 'new';
    protected const isProjectdetails = 'isProjectdetails';
    protected const editName = 'editText'; // prefix for the IDs of the text fields for editing the group name.
}