<?php

class SnakesHungerGames
{
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
     * PlayingMap constructor.
     * @param int $width
     * @param int $height
     */
    public function __construct(int $width, int $height)
    {
        $this->width = $width;
        $this->height = $height;
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
     * @return bool возвращает true если игра закончена и объявлен победитель
     */
    public function tick() : bool
    {
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
    public const
        ROTATION_LEFT = 'left',
        ROTATION_RIGHT = 'right',
        ROTATION_TOP = 'top',
        ROTATION_BOTTOM = 'bottom';

    const ALLOW_ROTATION = [
        self::ROTATION_BOTTOM,
        self::ROTATION_LEFT,
        self::ROTATION_RIGHT,
        self::ROTATION_TOP
    ];

    protected string $rotation;

    public function __construct(int $coordinateX, int $coordinateY, string $rotation = self::ROTATION_TOP)
    {
        $this->validateRotation($rotation);
        parent::__construct($coordinateX, $coordinateY);
    }


    protected function validateRotation(string $rotation)
    {
        if (!in_array(strtolower($rotation), self::ALLOW_ROTATION)) {
            throw new InvalidArgumentException(sprintf('Don\'t support rotation: %s allow: %s', $rotation, implode(',', self::ALLOW_ROTATION)));
        }

        $this->rotation = $rotation;
    }

    /**
     * @return string
     */
    public function getRotation() : string
    {
        return $this->rotation;
    }

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
}

class SnakeNode
{
    protected int $coordinateX;
    protected int $coordinateY;

    /**
     * @todo придумать как реализовать механизм проверки выхода змеи за пределы карты
     * SnakeNode constructor.
     * @param int $coordinateX
     * @param int $coordinateY
     */
    public function __construct(int $coordinateX, int $coordinateY)
    {
        $this->coordinateX = $coordinateX;
        $this->coordinateY = $coordinateY;
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
     * todo: невозможность установки ротации змеи, получить параметр ротации и пробросить в SnakeHead
     * todo: задекларировать где нибудь что в процессе создания змейки необходимо пустое пространство в одну клетку ( в зависимости от дефолтной длины змеи )
     * Snake constructor.
     * @param int $coordinateHeadX
     * @param int $coordinateHeadY
     */
    public function __construct(int $coordinateHeadX, int $coordinateHeadY)
    {
        $this->head = new SnakeHead($coordinateHeadX, $coordinateHeadY);
        $this->id = self::$countOfSnake++;
        $this->initializeBody();
    }

    public function getId() : int
    {
        return $this->id;
    }

    protected function initializeBody() : void
    {
        $this->body[] = new SnakeNode($this->head->getCoordinateX() + 0, $this->head->getCoordinateY() + 1);
    }

    /**
     * @todo: змейка может расти как аппендом ноды к голове, так и к хвосту, в зависимости от того находится ли голова у края карты
     * @return int
     */
    public function growUp() : int
    {
        $tailOfSnake = end($this->body);
        $this->body[] = new SnakeNode($tailOfSnake->getCoordinateX() + 0, $tailOfSnake->getCoordinateY() + 1);

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
        $preHeadNode = new SnakeNode($this->head->getCoordinateX(), $this->head->getCoordinateY());
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
$game->addSnake(new Snake(5, 5));
$game->addSnake(new Snake(5, 4));


while ($game->tick() == false);

echo 'Победитель змейка ' . $game->getWinner()->getId() . PHP_EOL;

var_dump($game);
