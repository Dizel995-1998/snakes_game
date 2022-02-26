<?php

class SnakesHungerGames
{
    public const DEFAULT_STEP_LIMIT = 20;

    protected int $width;
    protected int $height;

    /**
     * @var array<int, Snake>
     */
    protected array $snakes = [];

    /**
     * @var array<int, Snake>
     */
    protected array $deadSnakes = [];

    protected ?Snake $winner = null;

    /**
     * Ограничение на кол-во шагов змеек в ходе партии (чтобы не зациклиться, если боты или юзеры управляющие змейками слишком тупые)
     * @var int
     */
    protected int $stepLimit;

    /**
     * Счетчик шагов змеек
     * @var int
     */
    protected int $stepCounter = 0;

    /**
     * @fixme Нет проверки выхода змеек за пределы карты
     * PlayingMap constructor.
     * @param int $width
     * @param int $height
     * @param int $stepLimit число шагов доступных змейкам чтобы играть и определить победителя, иначе игра завершается
     */
    public function __construct(int $width, int $height, int $stepLimit = self::DEFAULT_STEP_LIMIT)
    {
        $this->width = $width;
        $this->height = $height;
        $this->stepLimit = $stepLimit;
    }

    /**
     * @todo: добавить проверку на содержание коллизий змеек
     *      змейки не могут иметь коллизии на момент инициализации карты
     * @param Snake $snake
     */
    public function addSnake(Snake $snake) : void
    {
        $this->validateSnakesCollision($snake);

        $this->snakes[$snake->getId()] = $snake;
    }

    public function addSnakes(Snake ...$snakes) : void
    {
        foreach ($snakes as $snake) {
            $this->validateSnakesCollision($snake);

            $this->snakes[$snake->getId()] = $snake;
        }
    }

    /**
     * Проверяет возможно ли добавление змеек в игру, нет ли змеек с одинаковым ID в пределе одной игровой партии
     * @param Snake $snakeToAdd
     */
    protected function validateSnakesCollision(Snake $snakeToAdd) : void
    {
        if (isset($this->snakes[$snakeToAdd->getId()])) {
            throw new InvalidArgumentException('You are trying overwrite exist snakes in game! collision with id: ' . $snakeToAdd->getId());
        }
    }

    public function getWidth() : int
    {
        return $this->width;
    }

    public function getHeight() : int
    {
        return $this->height;
    }

    public function getWinner() : ?Snake
    {
        return $this->winner;
    }

    /**
     *
     * @return bool возвращает true если игра закончена или/и объявлен победитель
     */
    public function tick() : bool
    {
        if ($this->stepCounter >= $this->stepLimit) {
            return true;
        }

        if (!($countOfSnakes = count($this->snakes))) {
            throw new RuntimeException('Can\'t start game without any snakes');
        }

        if ($countOfSnakes == 1) {
            $this->winner = current($this->snakes);
            return true;
        }

        /** Змейки сделавшие свой шаг */
        $snakesTookStep = [];

        foreach ($this->snakes as $snake) {
            $snake->makeStep();
            $this->checkSnakesEatEachOther($snakesTookStep, $snake);
            $snakesTookStep[] = $snake;
        }

        $this->stepCounter++;

        return false;
    }

    /**
     * @param Snake[] $snakesTookStep
     * @param Snake $currentSnake
     */
    protected function checkSnakesEatEachOther(array $snakesTookStep, Snake $currentSnake) : void
    {
        foreach ($snakesTookStep as $snake) {
            if ($currentSnake->isEatingAnotherSnake($snake)) {
                unset($this->snakes[$snake->getId()]);
                $this->deadSnakes[$snake->getId()] = $snake;

                // fixme: hardcode message out to stdout
                echo 'Ого, snake with id ' . $currentSnake->getId() . ' ест змейку с id: ' . $snake->getId() . PHP_EOL;
            }
        }
    }
}

class SnakeHead extends SnakeNode
{
    public function setCoordinateX(int $x) : self
    {
        $this->coordinateX = $x;
        return $this;
    }

    public function setCoordinateY(int $y) : self
    {
        $this->coordinateY = $y;
        return $this;
    }

    public function setRotation(string $rotation) : self
    {
        $this->validateRotation($rotation);
        $this->rotation = $rotation;
        return $this;
    }
}

class SnakeNode
{
    protected int $coordinateX;
    protected int $coordinateY;

    public const
        ROTATION_LEFT = 'left',
        ROTATION_RIGHT = 'right',
        ROTATION_TOP = 'top',
        ROTATION_BOTTOM = 'bottom';

    public const ALLOW_ROTATION = [
        self::ROTATION_BOTTOM,
        self::ROTATION_LEFT,
        self::ROTATION_RIGHT,
        self::ROTATION_TOP
    ];

    protected string $rotation;

    public function __construct(int $coordinateX, int $coordinateY, string $rotation = self::ROTATION_TOP)
    {
        $this->validateRotation($rotation);
        $this->coordinateX = $coordinateX;
        $this->coordinateY = $coordinateY;
    }

    protected function validateRotation(string $rotation)
    {
        if (!in_array(strtolower($rotation), self::ALLOW_ROTATION)) {
            throw new InvalidArgumentException(sprintf('Don\'t support rotation: %s allow: %s', $rotation, implode(',', self::ALLOW_ROTATION)));
        }

        $this->rotation = $rotation;
    }

    public function getRotation() : string
    {
        return $this->rotation;
    }

    public function getCoordinateX() : int
    {
        return $this->coordinateX;
    }

    public function getCoordinateY() : int
    {
        return $this->coordinateY;
    }
}

/**
 * Class Snake
 */
class Snake
{
    protected static int $countOfSnake = 0;
    protected int $id;
    protected SnakeHead $head;

    /**
     * @var SnakeNode[]
     */
    protected array $body = [];

    /**
     * todo: реализовать проверку на наличие пустого пространства вокруг змейки в процессе инициализации body
     * Snake constructor.
     * @param int $coordinateHeadX
     * @param int $coordinateHeadY
     * @param string $rotation
     */
    public function __construct(int $coordinateHeadX, int $coordinateHeadY, string $rotation = SnakeNode::ROTATION_TOP)
    {
        $this->head = new SnakeHead($coordinateHeadX, $coordinateHeadY, $rotation);
        $this->id = self::$countOfSnake++;
        $this->initializeBody();
    }

    public function getId() : int
    {
        return $this->id;
    }

    protected function initializeBody() : void
    {
        $this->body[] = $this->getNodeForAppend($this->head);
    }

    protected function getNodeForAppend(SnakeNode $node) : SnakeNode
    {
        $appendNode = null;

        /** Добавляем к хвосту ещё один отрезок, имеющий тот же вектор движения что хвостовая нода змейки **/
        switch ($node->getRotation()) {
            case SnakeNode::ROTATION_TOP:
                $appendNode = new SnakeNode($node->getCoordinateX(), $node->getCoordinateY() - 1, $node->getRotation());
                break;

            case SnakeNode::ROTATION_RIGHT:
                $appendNode = new SnakeNode($node->getCoordinateX() - 1, $node->getCoordinateY(), $node->getRotation());
                break;

            case SnakeNode::ROTATION_LEFT:
                $appendNode = new SnakeNode($node->getCoordinateX() + 1, $node->getCoordinateY(), $node->getRotation());
                break;

            case SnakeNode::ROTATION_BOTTOM:
                $appendNode = new SnakeNode($node->getCoordinateX(), $node->getCoordinateY() + 1, $node->getRotation());
                break;
        }

        if ($appendNode == null) {
            throw new RuntimeException(sprintf('Don\'t except value for rotation: %s, expect one of: %s', $node->getRotation(), implode(',', SnakeHead::ALLOW_ROTATION)));
        }

        return $appendNode;
    }

    /**
     * @fixme: змейка растёт посредством добавления к хвосту нового отрезка, поэтому возможны ситуации где хвост находится к края карты и увелечение длины змейки приведёт к фатальной ошибке
     * @return int
     */
    public function growUp() : int
    {
        $tailOfSnake = end($this->body);
        $this->body[] = $this->getNodeForAppend($tailOfSnake);

        return count($this->body);
    }

    protected function removeTail() : void
    {
        array_pop($this->body);
    }

    /**
     * Воспроизводит движение змеи в заданном направлении
     * @return void
     */
    public function makeStep() : void
    {
        /** Удаляем последнюю ноду тела змеи */
        $this->removeTail();

        /** Добавляем новую ноду в начало тела змеи, что бы скомпенсировать удаление хвоста */
        $preHeadNode = new SnakeNode($this->head->getCoordinateX(), $this->head->getCoordinateY(), $this->head->getRotation());
        array_unshift($this->body, $preHeadNode);

        /**
         * Сдвигаем голову змеи в соответствии с углом её движения
         * @todo проверить не выход за пределы карты
         **/
        switch ($this->head->getRotation()) {
            case SnakeHead::ROTATION_TOP:
                $this->head->setCoordinateY($this->head->getCoordinateY() + 1);
                break;

            case SnakeHead::ROTATION_RIGHT:
                $this->head->setCoordinateX($this->head->getCoordinateX() + 1);
                break;

            case SnakeHead::ROTATION_LEFT:
                $this->head->setCoordinateX($this->head->getCoordinateX() - 1);
                break;

            case SnakeHead::ROTATION_BOTTOM:
                $this->head->setCoordinateY($this->head->getCoordinateY() - 1);
                break;
        }
    }

    /**
     * @param string $rotation
     * @return Snake
     */
    public function setRotation(string $rotation) : self
    {
        $this->head->setRotation($rotation);
        return $this;
    }

    /**
     * @todo что делать если змейки столкнуться головами?
     * @param Snake $anotherSnake
     * @return bool
     */
    public function isEatingAnotherSnake(Snake $anotherSnake) : bool
    {
        foreach ($anotherSnake->body as $node) {
            if ($this->head->getCoordinateX() == $node->getCoordinateX() && $this->head->getCoordinateY() == $node->getCoordinateY()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @fixme кривой способ установки идентификаторов змеек, возможно неоднозначности ID из за дырок
     */
    public function __destruct()
    {
        self::$countOfSnake--;
    }
}

ini_set('xdebug.var_display_max_depth', 10);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 1024);

$game = new SnakesHungerGames(20, 20);

$firstSnake = new Snake(5, 5);
$secondSnake = new Snake(5, 4);

$game->addSnake($firstSnake);
$game->addSnake($secondSnake);

while ($game->tick() == false);

echo ($game->getWinner() ? 'Победитель змейка ' . $game->getWinner()->getId() : 'Победителя нет, превышен лимит итераций') . PHP_EOL;

var_dump($game);
