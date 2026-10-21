<?php

namespace App;

enum RoadmapStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
