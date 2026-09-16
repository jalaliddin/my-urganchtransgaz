<?php

namespace App\Http\Requests;

/**
 * A question update replaces its answers wholesale, so it validates
 * identically to creating one.
 */
class UpdateExamQuestionRequest extends StoreExamQuestionRequest
{
    //
}
