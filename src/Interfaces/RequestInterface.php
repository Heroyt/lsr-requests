<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace Lsr\Core\Requests\Interfaces;

use Lsr\Core\Routing\Interfaces\RouteInterface;
use JsonSerializable;

interface RequestInterface extends JsonSerializable
{

	public function __construct(array|string $query);

	public function handle() : void;

	public function getRoute() : ?RouteInterface;

}