<?php

namespace Application\ChatBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Application\ChatBundle\Document\Operator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Ismael Ambrosi<ismael@servergrove.com>
 */
class AddOperatorCommand extends BaseCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setDefinition(
                array(
                    new InputArgument('name', InputArgument::REQUIRED, 'The operator name'),
                    new InputArgument('email', InputArgument::REQUIRED, 'The operator e-mail'),
                    new InputArgument('password', InputArgument::REQUIRED, 'The admin password')))->setName('sglivechat:admin:add-operator')->setDescription('Create new Operator');
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $operator = $this->createOperator();

        $operator->setName($input->getArgument('name'));
        $operator->setEmail($input->getArgument('email'));
        $operator->setPasswd($input->getArgument('password'));

        $this->getDocumentManager()->persist($operator);
        $this->getDocumentManager()->flush();
    }

    public function createOperator()
    {
        return new Operator();
    }

}