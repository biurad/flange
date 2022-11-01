<?php

declare(strict_types=1);

/*
 * This file is part of DivineNii opensource projects.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 DivineNii (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions\Symfony\Form;

use Biurad\Http\Request;
use Biurad\Http\UploadedFile;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler as SymfonyHttpFoundationRequestHandler;
use Symfony\Component\Form\FormInterface;

class HttpFoundationRequestHandler extends SymfonyHttpFoundationRequestHandler
{
    /**
     * {@inheritdoc}
     */
    public function handleRequest(FormInterface $form, $request = null)
    {
        if ($request instanceof Request) {
            $request = $request->getRequest();
        }

        return parent::handleRequest($form, $request);
    }

    /**
     * {@inheritdoc}
     */
    public function isFileUpload($data): bool
    {
        return $data instanceof UploadedFile || parent::isFileUpload($data);
    }

    public function getUploadFileError($data): ?int
    {
        if ($data instanceof UploadedFile) {
            return $data->getError();
        }

        return parent::getUploadFileError($data);
    }
}
