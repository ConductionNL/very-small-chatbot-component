<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\ConversationRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;


/**
 * A conversation in where the questions are stored that have been asked to the receiver
 *
 * @ORM\Entity(repositoryClass="App\Repository\ConversationRepository")
 * @Gedmo\Loggable(logEntryClass="Conduction\CommonGroundBundle\Entity\ChangeLog")
 *
 */
class Conversation
{
    /**
     * @var UuidInterface The UUID identifier of this resource
     *
     * @example e2984465-190a-4562-829e-a8cca81aa35d
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    private $id;

    /**
     * dat uri zijn naar poroperty
     *
     * @var string The last question asked to the sender
     *
     * @example waar wil je naartoe verhuizen?
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $lastquestion;

    /**
     * @var string The request that is used for this conversation
     *
     * @example http://rtc.zaakonline.nl/9bd169ef-bc8c-4422-86ce-a0e7679ab67a
     *
     * @ORM\Column(type="string", length=255)
     */
    private $request;

    /**
     * @var string The request that is used for this conversation
     *
     * @example http://rtc.zaakonline.nl/9bd169ef-bc8c-4422-86ce-a0e7679ab67a
     *
     * @ORM\Column(type="string", length=255)
     */
    private $sender;

    /**
     * @var DateTime The moment this request was created
     *)
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreated;

    /**
     * @var DateTime The moment this request last Modified
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateModified;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getLastquestion(): ?string
    {
        return $this->lastquestion;
    }

    public function setLastquestion(string $lastquestion): self
    {
        $this->lastquestion = $lastquestion;

        return $this;
    }

    public function getRequestType(): ?string
    {
        return $this->requestType;
    }

    public function setRequestType(string $requestType): self
    {
        $this->requestType = $requestType;

        return $this;
    }

     public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

        public function setDateCreated(\DateTimeInterface $dateCreated): self
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

        public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

        public function setDateModified(\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }

}
