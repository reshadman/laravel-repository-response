<?php  namespace Bigsinoos\RepositoryResponse;

use ArrayAccess;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Bigsinoos\RepositoryResponse\Exceptions\MethodNotAllowedException;
use Bigsinoos\RepositoryResponse\Exceptions\MethodNotFoundException;

abstract class BaseEloquentEntity implements
    ArrayAccess, Arrayable,
    Jsonable, JsonSerializable,
    QueueableEntity, UrlRoutable {
        
    /**
     * Friend classes of the concrete entity|model
     *
     * @var array
     */
    protected $friends = [];

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Check friendship between this class and the caller
     *
     * @return bool
     * @throws MethodNotAllowedException
     */
    protected function checkFriendship()
    {
        $backtrace = debug_backtrace(null, 3); // The backtrace changes if you override this method

        if(isset($backtrace[2]['class']))
        {
            if($this->classIsFriend($class = $backtrace[2]['class']))
            {
                return true;
            }
        }

        throw new MethodNotAllowedException(
            "You are not allowed to perform this action due to Database external encapsulation."
        );
    }

    /**
     * Check that the given class is the firend of this entity or not
     *
     * @param $class
     * @return bool
     */
    protected function classIsFriend($class)
    {
        // If class it self is the list of friend classes
        if (in_array($class, $friends = $this->getFriends())) return true;

        // If class implements an interface that exists in
        // friend classes
        if(in_array($interfaces = class_implements($class), $friends)) return true;

        // If class extends a class that exists in the friends class list
        if(in_array($parents = class_parents($class), $friends)) return true;

        return false;
    }

    /**
     * Get friend classes array list
     * you can override this function to provide custom functionality
     *
     * @return array
     */
    protected function getFriends()
    {
        return $this->friends;
    }

    /**
     * Delegate the job to the related eloquent model if it
     * is allowed
     *
     * @param $method
     * @param $args
     * @return mixed
     * @throws MethodNotAllowedException
     * @throws MethodNotFoundException
     */
    public function __call($method, $args)
    {
        if(! is_null($model = $this->getProtectedModel()))
        {
            return call_user_func_array([$model, $method], $args);
        }

        throw new MethodNotFoundException(
            "No method with name [{$method}] found in the entity and the related eloquent model."
        );
    }

    /**
     * Get model after checking friendship
     *
     * @return Model
     * @throws MethodNotAllowedException
     */
    public function getProtectedModel()
    {
        $this->checkFriendship();

        return $this->model;
    }

    /**
     * Set model after checking friendship
     *
     * @param Model $model
     * @return $this
     * @throws MethodNotAllowedException
     */
    public function setModel(Model $model)
    {
        $this->checkFriendship();

        $this->model = $model; return $this;
    }

    /**
     * Get a property from the model if it exists and
     * the user class is friend
     *
     * @param $what
     * @return mixed|null
     */
    public function __get($what)
    {
        try{

            if(! is_null($this->model)) return $this->model->$what;

        }catch (MethodNotAllowedException $e){}

        return null;
    }

    /**
     * Create a new instance of the class
     *
     * @return static
     */
    public function newInstance()
    {
        return new static();
    }

    /**
     * Set everything on the model
     *
     * @param $what
     * @param $value
     */
    public function __set($what, $value)
    {
        if(is_null($this->model))
        {
            $this->model = $this->getNewModel();
        }

        $this->model->$what = $value;
    }

    /**
     * Get new eloquent model
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    abstract protected function getNewModel($attributes = []);

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getModel()->toArray();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $this->getModel()->offsetExists($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->getModel()->offsetGet($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->model->offsetSet($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->getModel()->offsetUnset($offset);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return $this->getModel()->toJson($options);
    }

    /**
     * Get the queueable identity for the entity.
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        return $this->getModel()->getRouteKey();
    }

    /**
     * Get the value of the model's route key.
     *
     * @return mixed
     */
    public function getRouteKey()
    {
        return $this->getModel()->getRouteKey();
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return $this->getModel()->getRouteKeyName();
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    function jsonSerialize()
    {
        return $this->getModel()->jsonSerialize();
    }

    /**
     * Get eloquent model
     *
     * @return Model
     */
    protected function getModel()
    {
        return is_null($this->model) ? ($this->model = $this->getNewModel()) : $this->model;
    }
}
