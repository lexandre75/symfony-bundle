<?php

/*
 * This file is part of the ACME PHP library.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Bundle\Acme\Certificate;

use AcmePhp\Bundle\Acme\Certificate\Parser\ParserInterface;
use AcmePhp\Bundle\Acme\Certificate\Formatter\FormatterInterface;
use AcmePhp\Bundle\Acme\Certificate\Storage\CertificateStorageFactory;
use AcmePhp\Bundle\Acme\Domain\DomainConfiguration;
use AcmePhp\Core\Ssl\Certificate;
use AcmePhp\Core\Ssl\KeyPair;

/**
 * Persist and hydrate certificate.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CertificateRepository
{
    /** @var CertificateStorageFactory */
    protected $storageFactory;

    /** @var FormatterInterface[] */
    protected $formatters;

    /** @var ParserInterface[] */
    protected $parsers;

    /**
     * @param CertificateStorageFactory $storageFactory
     * @param FormatterInterface[]      $formatters
     * @param ParserInterface[]         $parsers
     */
    public function __construct(CertificateStorageFactory $storageFactory, array $formatters, array $parsers)
    {
        $this->storageFactory = $storageFactory;
        $this->formatters = $formatters;
        $this->parsers = $parsers;
    }

    /**
     * Store the given certificate in several formats.
     *
     * @param DomainConfiguration $configuration
     * @param Certificate         $certificate
     * @param KeyPair             $domainKeyPair
     */
    public function persistCertificate(
        DomainConfiguration $configuration,
        Certificate $certificate,
        KeyPair $domainKeyPair
    ) {
        $storage = $this->storageFactory->createCertificateStorage($configuration->getDomain());
        $storage->backup();
        foreach ($this->formatters as $formatter) {
            $storage->saveCertificateFile($formatter->getName(), $formatter->format($certificate, $domainKeyPair));
        }
    }

    /**
     * Clear the persisted certificates.
     *
     * @param DomainConfiguration $configuration
     */
    public function clearCertificate(DomainConfiguration $configuration)
    {
        $storage = $this->storageFactory->createCertificateStorage($configuration->getDomain());
        foreach ($this->formatters as $formatter) {
            $storage->removeCertificateFile($formatter->getName());
        }
    }

    /**
     * Return whether or not a certificate exists for the given configuration.
     *
     * @param DomainConfiguration $configuration
     *
     * @return bool
     */
    public function hasCertificate(DomainConfiguration $configuration)
    {
        $storage = $this->storageFactory->createCertificateStorage($configuration->getDomain());
        foreach ($this->formatters as $formatter) {
            if (!$storage->hasCertificateFile($formatter->getName())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return certificate's data.
     *
     * @param DomainConfiguration $configuration
     *
     * @return CertificateMetadata
     */
    public function loadCertificate(DomainConfiguration $configuration)
    {
        $metadata = new CertificateMetadata($configuration->getDomain());
        $storage = $this->storageFactory->createCertificateStorage($configuration->getDomain());
        /** @var ParserInterface $parser */
        foreach ($this->parsers as $parser) {
            $metadata->merge($parser->parse($storage->loadCertificateFile($parser->getName())));
        }

        return $metadata;
    }
}
