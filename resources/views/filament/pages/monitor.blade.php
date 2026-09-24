<x-filament-panels::page>
    {{ $this->filtersForm }}

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :widgets="$this->getVisibleWidgets()"
        :data="['pageFilters' => $this->filters]"
    />
</x-filament-panels::page>
