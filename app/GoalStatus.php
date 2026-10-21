<?php

namespace App;

enum GoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Retired = 'retired';
}
