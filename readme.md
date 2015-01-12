# Laravel Repository Response
The trend of Repository pattern made all of us to implement it in our Laravel projects. But We all have been implementing it incorrect or at least incomplete. The problem is that in true Repository pattern in addition to the input contracts( Our repositoy interfaces ) We make a deal on our method response, which is not possible in dynamic languages like PHP (It has been added to PHP7). This package helps you to implement the true pattern approximately.

> We should know that there is no need to implement the full true pattern, patterns have been made to solve us problems, not to be the problem itself. The incomplete pattern that is being trended is good enough for easier testing.

### How it works
The real pattern interacts with Entities instead of our Eloquent models. so with ```Bigsinoos\RepositoryResponse\BaseEloquentEntity``` this problem is solved. So instead of messaging the ```Eloquent``` models, We must pass our entities. when using Eloquent Repsitories, we can put the model inside our entity which only is accssible by our ```EloquentRepository``` implementation with the help of **Friend / Sibling** classes.

### Friend Classes and debug_back_trace function

A friend classes in the scope of object oriented design, can break their encapsulation layer, for example they can call ```protected``` method of each other. PHP does not support friend classes at all.

When we return Eloquent models from our repository methods, we allow external access to our database layer, which should be done through our repository contracts, this simply breaks the pattern, to solve this problem instead of returning Eloquent models from repository, we simply create a new Entity for the Model, and Wrap it arround the model, So everytime a method or propery is called from the entity the entity behvaes like below :
  * Check that it is being called from a friend class or not (with debug_backtract).
  * If so, calls the method from it's model, else it will throw an access denied exception.

When trying to set a property on an Entity it is set on its models.

By this you can pass an Entity to multiple repositories and their eloquent models can be used as long as they are friend with each other, the friendship is defined in the eloquent implementation of the entity.

But this also breaks another rule. ** Just like as concrete implementations of reposutory classes we should define different entities for each implmenetation.** (For example ```MongoBlogeEntity```, ```EloquentBlogEntity```, ```DoctorineBlogEntity``` but as long as you make a ```BlogEntityInterface``` contract for them. there isn't any problem, at least it allows to switch between different implmentaions, which was not possible in our previous methodology (returning eloquent models from methods)).

### Usage

A user repository workflow will be as below :
 * UserEntityContract.php
 * EloquentUserEntity.php : this class should extends ```Bigsinoos\RepositoryResponse\BaseEloquentEntity``` class, to get the class friendship functionality.
 * User.php
 * UserRepositoryContract.php
 * EloquentUserRepository.php

UserEntityContract.php

```php
<?php
interface UserEntityContract {}
```
EloquentUserEntity.php
```php
class EloquentUserEntity extends \Bigsinoos\RepositoryResponse\BaseEloquentEntity implements \UserEntityContract {
        /**
         * Friends of the user repository
         *
         * @var array
         */
        protected $friends = [
            'EloquentUserRepository'
        ];
        
        /**
         * Get new eloquent model for each entity, it is used when you set 
         * an attribute on the entity to the entity will make an instance of the model
         * and sets the attribute on it.
         *
         * @param array $attributes
         * @return \Illuminate\Database\Eloquent\Model
         */
        protected function getNewModel($attributes = [])
        {
            return new \User($attributes);
        }
    
}
```
User.php

```php
<?php

class User extends \Eloquent {
    
    protected $table = 'users';
    
}
```

UserRepositoryContract.php
```php
interface UserRepositoryContract {
    /**
     * Finds the user gieven his/her id
     * 
     * @param int $id
     * @return \UserEntityContract
     */
    public function findById($id);
    
    /**
     * Take a collection of users
     *
     * @param int $howMuch
     * @param bool $decreasing
     * @return \Illuminate\Support\Collection
     */
    public function take($howMuch = 10, $sortBy = 'created_at', $decreasing = true);
}
```
EloquentUserRepository.php
```php
class EloquentUserRepository implements UserRepositoryContract {
    
    protected $userEntity;
    
    public function __construct(\UserEntityContract $userEntity)
    {
            $this->userEntity = $userEntity;
    }
    
    public function findById($id)
    {
        $model = $this->userEntity->getModel(); // Only friend class can do this.
        
        $found = $model->newInstance()->findOrFail($id);
        
        $entity = $this->userEntity->newInstance();
        
        $entity->setModel($found); // Only friend class can do this.
        
        return $entity;
    }
    
    public function take($howMuch = 10, $sortBy = 'created_at', $decreasing = true)
    {
        $collection = $this->userEntity
            ->getModel()
            ->newInstance()
            ->orderBy($sortBy, (bool) $decreasing)
            ->take((int) $howMuch)->get();
        
        // Don't do this for large data sets.
        return $this->buildEntityCollection($collection);
    }
    
    protected function buildEntityCollection(\Illuminate\Support\Collection $collection)
    {
        $class = 'Illuminate\Support\Collection';
        $items = [];
        
        $collection->each(function($item)){
        
                $entity = $this->userEntity->newInstance();
                
                $entity->setModel($item);
                
                $items [] = $entity;
        });
        return app($class)->make($items);
    }
}
```
ExampleController.php
```php
class ExampleController extends \BaseController {
    
    protected $userRepo;
    
    public function __construct(\UserRepositoryContract $userRepo)
    {
        $this->userRepo = $userRepo;
    }
    
    public function show($id)
    {
        $user = $this->userRepo->findById($id);
        
        // $user->getModel(); throws a MethodNotAllowedException
        // $user->delete(); throws a MethodNotAllowedException
        // $user->somethingStupid(); throws as MethodNotFoundException
        
        return view('user', compact('user');
    }
}
```
### Exceptions
 * ```Bigsinoos\RepositoryResponse\Exceptions\EntityExceptionInterface``` all exceptions are implmenting this contract.
 * ```Bigsinoos\RepositoryResponse\Exceptions\EntityException``` a basic implmentation of the above contract, for unexpected behaviours.
 * ```Bigsinoos\RepositoryResponse\Exceptions\MethodNotAllowedException``` if the eloquent model is tried to being accessed outside a friend class this will be thrown.
 * ```Bigsinoos\RepositoryResponse\Exceptions\MethodNotAllowedException``` if the requested could not be found on the model class this will be thrown.
 
> It is really OK to return simple eloquent models from method respositories, they are very usefull when we want to write tests, but they don't allow us to switch between different implmenetation becauase we are breaking the pattern.