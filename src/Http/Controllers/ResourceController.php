<?php

namespace Benjacho\BelongsToManyField\Http\Controllers;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;

class ResourceController {

  public function index(NovaRequest $request, $parent, $relationship, $optionsLabel, $dependsOnValue = NULL, $dependsOnKey = NULL) {
    $resourceClass = $request->newResource();
    $fieldCollection = $resourceClass->availableFields($request);
    $field = $fieldCollection->reduce(function ($carry, $item) {
      if ($item->component === 'nova-dependency-container') {
        $carry = $carry->merge($item->meta()['fields']);
      }
      else {
        $carry->push($item);
      }
      return $carry;
    }, FieldCollection::make([]))
      ->where('component', 'BelongsToManyField')
      ->where('attribute', $relationship)
      ->first();

    $query = $field->buildAttachableQuery($request, FALSE);

    if ($request->dependsOnValue) {
      $query = $query->where(
        $request->dependsOnKey,
        $request->dependsOnValue
      );
    }

    return $query->get()
      ->mapInto($field->resourceClass)
      ->filter(function ($resource) use ($request, $field) {
        return $request->newResource()->authorizedToAttach($request, $resource->resource);
      })->map(function ($resource) use ($field, $optionsLabel) {
        return $field->formatDisplayValues($resource, $optionsLabel);
      })->sortBy($optionsLabel)->values();
  }
}
