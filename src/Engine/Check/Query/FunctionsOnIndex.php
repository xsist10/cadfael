<?php

declare(strict_types=1);

namespace Cadfael\Engine\Check\Query;

use Cadfael\Engine\Check;
use Cadfael\Engine\Entity\Query;
use Cadfael\Engine\Report;

class FunctionsOnIndex implements Check
{
    public function supports($entity): bool
    {
        return $entity instanceof Query;
    }

    public function run($entity): ?Report
    {
        $messages = [];
        $status = Report::STATUS_OK;

        // If you're performing a query where the WHERE statement contains an index column that is first modified
        // by a function, then the index cannot be used.
        $functions = $entity->fetchColumnsModifiedByFunctions();
        if (!empty($functions)) {
            foreach ($functions as $function) {
                $table = $function['table'];
                $column = $function['column'];

                foreach ($table->getIndexes() as $index) {
                    foreach ($index->getColumns() as $indexColumn) {
                        if ($column->getName() === $indexColumn->getName()) {
                            $messages[] = "Column $column is an index.";
                            $status = Report::STATUS_WARNING;
                        }
                    }
                }
            }
        }

        if ($status != Report::STATUS_OK) {
            $messages[] = "By first modified an index with a function prevents it from being used.";
            $messages[] = "To fix this, move the function to the other side of the expression if possible.";
        }

        return new Report(
            $this,
            $entity,
            $status,
            $messages
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function getReferenceUri(): string
    {
        return 'https://github.com/xsist10/cadfael/wiki/Functions-On-Index';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getName(): string
    {
        return 'Function on index column in WHERE statement';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return "A column that is part of an index cannot be used in the WHERE statement if it is being modified by a "
             . "function.";
    }
}
