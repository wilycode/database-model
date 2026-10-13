<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests\Model;

enum ArticleStatus: int
{
    case Draft = 1;
    case Published = 2;
    case Deleted = 3;
    case AwaitingReview = 4;
    case ToImprove = 5;
    case NotPublished = 6;

}
