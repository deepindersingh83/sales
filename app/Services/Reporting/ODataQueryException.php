<?php

namespace App\Services\Reporting;

/** A client error in an OData query option; rendered as an OData 400 error. */
class ODataQueryException extends \InvalidArgumentException {}
