<?php  namespace Bigsinoos\RepositoryResponse\Exceptions;

class MethodNotAllowedException extends EntityException implements EntityExceptionInterface{
    # When external is not allowed to call the Model method
}