<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A message that as part of a converstation.
 *
 * @ApiResource(
 *     normalizationContext={"groups"={"read"}, "enable_max_depth"=true},
 *     denormalizationContext={"groups"={"write"}, "enable_max_depth"=true},
 *     itemOperations={
 *          "get",
 *     },
 *     collectionOperations={
 *         "post_message_to_proccess"={
 *              "path"="/procces/{id}/message",
 *              "method"="post",
 *              "swagger_context" = {
 *                  "summary"="Audittrail",
 *                  "description"="Gets the audit trail for this resource"
 *              }
 *          }
 *     }
 * )
 */
class Message
{
    /**
     * @var string The url of a person who is the sender(the requester)
     *
     * @example https://person/1
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @Groups({"write"})
     */
    private $sender;

    /**
     * @var string The message of the sender
     *
     * @example Ik wil verhuizen
     *
     * @Assert\NotNull
     * @Assert\Length(
     *      max = 255
     * )
     * @Groups({"write"})
     */
    private $message;

    /**
     * @var string The to the sender
     *
     * @example Ik wil verhuizen
     *
     * @Groups({"read"})
     */
    private $responce;

    public function getId(): ?string
    {
        return '1234';
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getResponce(): ?string
    {
        return $this->responce;
    }

    public function setResponce(string $responce): self
    {
        $this->responce = $responce;

        return $this;
    }
}
