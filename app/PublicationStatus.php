<?php

namespace App;

/** Supported publication states for managed Islamic content. */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
