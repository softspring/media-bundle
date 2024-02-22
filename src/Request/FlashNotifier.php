<?php

namespace Softspring\MediaBundle\Request;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FlashNotifier
{
    public function __construct(
        protected RequestStack $requestStack,
        protected TranslatorInterface $translator,
    ) {
    }

    public function add(string $type, string $message): void
    {
        $this->requestStack->getSession()->getFlashBag()->add($type, $message);
    }

    public function addTrans(string $type, string $message, array $parameters = [], string $domain = null, string $locale = null): void
    {
        $this->add($type, $this->translator->trans($message, $parameters, $domain, $locale));
    }
}
