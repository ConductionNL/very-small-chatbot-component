<?php

// Conduction/CommonGroundBundle/Service/RequestTypeService.php

/*
 * This file is part of the Conduction Common Ground Bundle
 *
 * (c) Conduction <info@conduction.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;

class MessageService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function extendRequestType(Message $message)
    {

    }
}
