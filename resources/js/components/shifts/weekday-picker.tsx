import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { WEEKDAYS } from '@/lib/shifts';

type WeekdayPickerProps = {
    value: number[];
    onChange: (days: number[]) => void;
};

/**
 * Weekday chips. No selection means every day.
 */
export function WeekdayPicker({ value, onChange }: WeekdayPickerProps) {
    return (
        <ToggleGroup
            type="multiple"
            variant="outline"
            size="sm"
            value={value.map(String)}
            onValueChange={(days) => onChange(days.map(Number))}
            className="flex-wrap"
        >
            {WEEKDAYS.map((label, day) => (
                <ToggleGroupItem
                    key={label}
                    value={String(day)}
                    className="px-3"
                >
                    {label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
