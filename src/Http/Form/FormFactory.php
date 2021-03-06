<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2020 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Http\Form;

use Symfony\Component\Form;

final class FormFactory
{
    public static function create(Form\FormFactoryInterface $formFactory, $formType): Form\FormInterface
    {
        return $formFactory->createBuilder($formType)->getForm();
    }
}
